<?php

/**
 * Slides - Simple slideshow maker and display
 * Copyright (C) 2025 Josh at WLTechBlog
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

declare(strict_types=1);

session_start();

define('BASE_DIR', __DIR__);
define('SLIDESHOWS_DIR', BASE_DIR . '/slideshows');
define('PUBLIC_DIR', BASE_DIR . '/public');

$config = [];
$configFile = BASE_DIR . '/config.php';
if (file_exists($configFile)) {
    $config = include $configFile;
}

define('ADMIN_PASSWORD', $config['admin_password'] ?? null);

if (!is_dir(SLIDESHOWS_DIR)) {
    mkdir(SLIDESHOWS_DIR, 0755, true);
}

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = dirname($_SERVER['SCRIPT_NAME']);
if ($base_path !== '/') {
    $request_uri = substr($request_uri, strlen($base_path));
}

$request_uri = trim($request_uri, '/');

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if (empty($request_uri)) {
    requireAuth();
    handleHome();
} elseif (preg_match('#^play/([a-zA-Z0-9_-]+)$#', $request_uri, $matches)) {
    handlePlay($matches[1]);
} elseif (preg_match('#^edit/([a-zA-Z0-9_-]+)$#', $request_uri, $matches)) {
    requireAuth();
    handleEdit($matches[1]);
} elseif ($request_uri === 'api/save-slideshow') {
    requireAuth();
    handleSaveSlideshow();
} elseif (preg_match('#^api/delete/([a-zA-Z0-9_-]+)$#', $request_uri, $matches)) {
    requireAuth();
    handleDeleteSlideshow($matches[1]);
} else {
    http_response_code(404);
    echo "Not found";
}

function requireAuth(): void
{
    if (ADMIN_PASSWORD === null) {
        return;
    }

    if (!isset($_SESSION['slides_authenticated']) || $_SESSION['slides_authenticated'] !== true) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
            if ($_POST['password'] === ADMIN_PASSWORD) {
                $_SESSION['slides_authenticated'] = true;
                return;
            }
        }

        showLoginForm();
        exit;
    }
}

function showLoginForm(): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background-color: #f5f5f5; display: flex; align-items: center; justify-content: center; height: 100vh; }
            .login-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
            h1 { margin-bottom: 30px; text-align: center; color: #333; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 8px; color: #333; font-weight: bold; }
            input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
            button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
            button:hover { background-color: #0056b3; }
            .error { color: #dc3545; margin-top: 10px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>Admin Login</h1>
            <form method="POST">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit">Login</button>
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <div class="error">Invalid password</div>
                <?php endif; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
}

function handleHome(): void
{
    $slideshows = getSlideshows();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Slideshows</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 20px; }
            .container { max-width: 1000px; margin: 0 auto; }
            .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
            h1 { color: #333; margin: 0; }
            .logout-btn { padding: 8px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
            .logout-btn:hover { background-color: #5a6268; }
            .new-slideshow { margin-bottom: 30px; }
            .new-slideshow button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            .new-slideshow button:hover { background-color: #0056b3; }
            .slideshows-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
            .slideshow-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .slideshow-card h3 { padding: 15px; background-color: #f9f9f9; border-bottom: 1px solid #eee; }
            .slideshow-card .actions { display: flex; gap: 10px; padding: 15px; background-color: #f9f9f9; }
            .slideshow-card a, .slideshow-card button { flex: 1; padding: 10px; text-align: center; text-decoration: none; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
            .slideshow-card a:hover, .slideshow-card button:hover { background-color: #0056b3; }
            .slideshow-card .delete-btn { background-color: #dc3545; }
            .slideshow-card .delete-btn:hover { background-color: #c82333; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Slideshows</h1>
                <?php if (ADMIN_PASSWORD !== null): ?>
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="logout" value="1" class="logout-btn">Logout</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="new-slideshow">
                <button onclick="createNewSlideshow()">+ New Slideshow</button>
            </div>
            
            <div class="slideshows-grid">
                <?php foreach ($slideshows as $slug => $slideshow): ?>
                    <div class="slideshow-card">
                        <h3><?php echo htmlspecialchars($slideshow['title'] ?? 'Untitled'); ?></h3>
                        <div class="actions">
                            <a href="/play/<?php echo htmlspecialchars($slug); ?>">Play</a>
                            <a href="/edit/<?php echo htmlspecialchars($slug); ?>">Edit</a>
                            <button class="delete-btn" onclick="deleteSlideshow('<?php echo htmlspecialchars($slug); ?>')">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
            function createNewSlideshow() {
                const title = prompt('Enter slideshow title:');
                if (title) {
                    const slug = title.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
                    window.location.href = '/edit/' + slug;
                }
            }
            
            function deleteSlideshow(slug) {
                if (confirm('Are you sure you want to delete this slideshow?')) {
                    fetch('/api/delete/' + slug, { method: 'POST' })
                        .then(r => location.reload());
                }
            }
        </script>
    </body>
    </html>
    <?php
}

function handlePlay(string $slug): void
{
    $slideshow = loadSlideshow($slug);
    if (!$slideshow) {
        http_response_code(404);
        echo "Slideshow not found";
        return;
    }
    
    $slides = $slideshow['slides'] ?? [];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($slideshow['title'] ?? 'Slideshow'); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; }
            .slide { display: none; width: 100vw; height: 100vh; background-color: white; }
            .slide.active { display: flex; }
            .slide-content { display: flex; width: 100%; height: 100%; }
            .slide-image { flex: 0 0 50%; background-color: #f0f0f0; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .slide-image img { max-width: 100%; max-height: 100%; object-fit: contain; }
            .slide-text { flex: 0 0 50%; display: flex; align-items: center; justify-content: center; padding: 40px; background-color: white; }
            .slide-text-content { color: black; font-size: 24px; line-height: 1.6; }
            .controls { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); display: flex; gap: 20px; z-index: 100; }
            button { padding: 10px 20px; font-size: 16px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background-color: #0056b3; }
            .slide-counter { position: fixed; top: 20px; right: 20px; font-size: 18px; color: #333; background: white; padding: 10px 15px; border-radius: 4px; }
            .home-btn { position: fixed; top: 20px; left: 20px; padding: 10px 15px; }
        </style>
    </head>
    <body>
        <a href="/" class="home-btn">← Home</a>
        <div class="slide-counter"><span id="current">1</span> / <span id="total"><?php echo count($slides); ?></span></div>
        
        <?php foreach ($slides as $index => $slide): ?>
            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>">
                <div class="slide-content">
                    <div class="slide-image">
                        <?php if (!empty($slide['image'])): ?>
                            <img src="<?php echo htmlspecialchars($slide['image']); ?>" alt="Slide image">
                        <?php endif; ?>
                    </div>
                    <div class="slide-text">
                        <div class="slide-text-content">
                            <?php echo nl2br(htmlspecialchars($slide['text'] ?? '')); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="controls">
            <button onclick="previousSlide()">← Previous</button>
            <button onclick="nextSlide()">Next →</button>
        </div>
        
        <script>
            let currentSlide = 0;
            const slides = document.querySelectorAll('.slide');
            const totalSlides = slides.length;
            
            function showSlide(n) {
                slides.forEach(s => s.classList.remove('active'));
                slides[n].classList.add('active');
                document.getElementById('current').textContent = n + 1;
            }
            
            function nextSlide() {
                currentSlide = (currentSlide + 1) % totalSlides;
                showSlide(currentSlide);
            }
            
            function previousSlide() {
                currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
                showSlide(currentSlide);
            }
            
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight') nextSlide();
                if (e.key === 'ArrowLeft') previousSlide();
            });
        </script>
    </body>
    </html>
    <?php
}

function handleEdit(string $slug): void
{
    $slideshow = loadSlideshow($slug);
    if (!$slideshow) {
        $slideshow = ['title' => ucwords(str_replace('-', ' ', $slug)), 'slides' => []];
    }
    
    $slides = $slideshow['slides'] ?? [];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Edit: <?php echo htmlspecialchars($slideshow['title']); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
            .editor { display: flex; height: 100vh; }
            .sidebar { width: 300px; background-color: #333; color: white; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; }
            .sidebar-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
            .sidebar h2 { margin: 0; }
            .logout-btn { padding: 6px 10px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 3px; border: none; cursor: pointer; font-size: 12px; }
            .logout-btn:hover { background-color: #5a6268; }
            .sidebar-content { flex: 1; overflow-y: auto; }
            .slide-list { list-style: none; }
            .slide-item { padding: 10px; margin-bottom: 10px; background-color: #444; border-radius: 4px; cursor: pointer; }
            .slide-item.active { background-color: #007bff; }
            .add-slide-btn { width: 100%; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 20px; }
            .add-slide-btn:hover { background-color: #218838; }
            .content { flex: 1; padding: 40px; overflow-y: auto; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 8px; font-weight: bold; }
            input, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: Arial, sans-serif; }
            textarea { min-height: 300px; resize: vertical; }
            .buttons { display: flex; gap: 10px; margin-top: 30px; }
            button { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background-color: #0056b3; }
            .delete-slide-btn { background-color: #dc3545; }
            .delete-slide-btn:hover { background-color: #c82333; }
            .title-input { font-size: 24px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="editor">
            <div class="sidebar">
                <div class="sidebar-header">
                    <h2><?php echo htmlspecialchars($slideshow['title']); ?></h2>
                    <?php if (ADMIN_PASSWORD !== null): ?>
                        <form method="POST" style="margin: 0;">
                            <button type="submit" name="logout" value="1" class="logout-btn">Logout</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="sidebar-content">
                    <button class="add-slide-btn" onclick="addSlide()">+ Add Slide</button>
                    <ul class="slide-list" id="slideList">
                        <?php foreach ($slides as $index => $slide): ?>
                            <li class="slide-item <?php echo $index === 0 ? 'active' : ''; ?>" onclick="selectSlide(<?php echo $index; ?>)">
                                Slide <?php echo $index + 1; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="content">
                <form id="editForm">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" class="title-input" value="<?php echo htmlspecialchars($slideshow['title']); ?>">
                    </div>
                    
                    <div id="slideContent">
                        <?php if (count($slides) > 0): ?>
                            <?php $slide = $slides[0]; ?>
                            <div class="form-group">
                                <label for="slideImage">Image URL</label>
                                <input type="url" id="slideImage" value="<?php echo htmlspecialchars($slide['image'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="slideText">Text</label>
                                <textarea id="slideText"><?php echo htmlspecialchars($slide['text'] ?? ''); ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="buttons">
                        <button type="button" onclick="saveSlideshow()">Save</button>
                        <button type="button" onclick="playSlideshow()">Play</button>
                        <button type="button" class="delete-slide-btn" onclick="deleteSlide()" id="deleteSlideBtn" style="display: <?php echo count($slides) > 0 ? 'block' : 'none'; ?>">Delete Slide</button>
                        <a href="/" style="padding: 10px 20px; text-decoration: none; background-color: #6c757d; color: white; border-radius: 4px; display: flex; align-items: center;">Home</a>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
            const slug = '<?php echo htmlspecialchars($slug); ?>';
            let slides = <?php echo json_encode($slides); ?>;
            let currentSlideIndex = 0;
            
            function selectSlide(index) {
                if (currentSlideIndex < slides.length) {
                    slides[currentSlideIndex].image = document.getElementById('slideImage').value;
                    slides[currentSlideIndex].text = document.getElementById('slideText').value;
                }
                
                currentSlideIndex = index;
                const slide = slides[index] || { image: '', text: '' };
                
                document.getElementById('slideImage').value = slide.image || '';
                document.getElementById('slideText').value = slide.text || '';
                
                document.querySelectorAll('.slide-item').forEach((el, i) => {
                    el.classList.toggle('active', i === index);
                });
            }
            
            function addSlide() {
                if (currentSlideIndex < slides.length) {
                    slides[currentSlideIndex].image = document.getElementById('slideImage').value;
                    slides[currentSlideIndex].text = document.getElementById('slideText').value;
                }
                
                slides.push({ image: '', text: '' });
                currentSlideIndex = slides.length - 1;
                
                const li = document.createElement('li');
                li.className = 'slide-item active';
                li.textContent = 'Slide ' + slides.length;
                li.onclick = () => selectSlide(slides.length - 1);
                document.getElementById('slideList').appendChild(li);
                
                document.getElementById('slideImage').value = '';
                document.getElementById('slideText').value = '';
                document.getElementById('deleteSlideBtn').style.display = 'block';
                
                document.querySelectorAll('.slide-item').forEach((el, i) => {
                    el.classList.toggle('active', i === currentSlideIndex);
                });
            }
            
            function deleteSlide() {
                if (slides.length <= 1) {
                    alert('Cannot delete the last slide');
                    return;
                }
                
                slides.splice(currentSlideIndex, 1);
                document.querySelectorAll('.slide-item')[currentSlideIndex]?.remove();
                
                currentSlideIndex = Math.min(currentSlideIndex, slides.length - 1);
                selectSlide(currentSlideIndex);
                
                if (slides.length === 0) {
                    document.getElementById('deleteSlideBtn').style.display = 'none';
                }
            }
            
            function saveSlideshow() {
                if (currentSlideIndex < slides.length) {
                    slides[currentSlideIndex].image = document.getElementById('slideImage').value;
                    slides[currentSlideIndex].text = document.getElementById('slideText').value;
                }
                
                const data = {
                    slug: slug,
                    title: document.getElementById('title').value,
                    slides: slides
                };
                
                fetch('/api/save-slideshow', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                }).then(r => alert('Slideshow saved!'));
            }
            
            function playSlideshow() {
                saveSlideshow();
                setTimeout(() => window.location.href = '/play/' + slug, 500);
            }
        </script>
    </body>
    </html>
    <?php
}

function handleSaveSlideshow(): void
{
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['slug'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['slug']);
    $file = SLIDESHOWS_DIR . '/' . $slug . '.json';
    
    $slideshow = [
        'title' => $data['title'] ?? 'Untitled',
        'slides' => $data['slides'] ?? []
    ];
    
    file_put_contents($file, json_encode($slideshow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    echo json_encode(['success' => true]);
}

function handleDeleteSlideshow(string $slug): void
{
    header('Content-Type: application/json');
    
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
    $file = SLIDESHOWS_DIR . '/' . $slug . '.json';
    
    if (file_exists($file)) {
        unlink($file);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
}

function getSlideshows(): array
{
    $slideshows = [];
    
    if (!is_dir(SLIDESHOWS_DIR)) {
        return $slideshows;
    }
    
    foreach (scandir(SLIDESHOWS_DIR) as $file) {
        if (substr($file, -5) === '.json') {
            $slug = substr($file, 0, -5);
            $slideshow = loadSlideshow($slug);
            if ($slideshow) {
                $slideshows[$slug] = $slideshow;
            }
        }
    }
    
    return $slideshows;
}

function loadSlideshow(string $slug): ?array
{
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
    $file = SLIDESHOWS_DIR . '/' . $slug . '.json';
    
    if (!file_exists($file)) {
        return null;
    }
    
    $content = file_get_contents($file);
    return json_decode($content, true);
}
?>
