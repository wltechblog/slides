# Slides

A simple PHP-based slideshow maker and display tool. Create, edit, and share slideshows with a clean, professional interface.

## Features

- **Create & Edit Slideshows**: Full WYSIWYG editor with password protection
- **Beautiful Playback**: White background with images on the left (scaled to fit) and text on the right
- **Navigation**: Arrow keys or button navigation between slides
- **Public Sharing**: Play any slideshow without authentication
- **JSON Storage**: Slideshows stored as simple JSON files
- **URL-based Routing**: Clean URLs for each slideshow

## Requirements

- PHP 8.0 or higher
- Web server with URL rewriting support (Apache with mod_rewrite or Nginx)
- Writable directory for slideshow storage

## Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/wltechblog/slides.git
   cd slides
   ```

2. **Create the configuration file**:
   ```bash
   cp config.example.php config.php
   ```

3. **Edit the configuration file**:
   ```bash
   nano config.php
   ```
   Set a strong admin password in the `admin_password` field.

4. **Create slideshows directory**:
   ```bash
   mkdir slideshows
   chmod 755 slideshows
   ```

5. **Configure your web server** (see deployment section below)

## Configuration

Configuration is managed through the `config.php` file. Copy `config.example.php` to `config.php` and customize as needed:

```php
return [
    'admin_password' => 'your-secure-password-here',
];
```

### Configuration Options

- **admin_password**: Password required for creating, editing, and deleting slideshows. Set to `null` to disable authentication (not recommended for production).

### Environment Variables (Optional)

For enhanced security in production environments, use environment variables instead of hardcoding credentials:

```php
return [
    'admin_password' => $_ENV['SLIDES_ADMIN_PASSWORD'] ?? null,
];
```

Then set the environment variable:
```bash
export SLIDES_ADMIN_PASSWORD='your-secure-password-here'
```

**Note:** `config.php` is automatically excluded from git and should never be committed to version control.

## Deployment

### Apache

1. **Enable mod_rewrite**:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

2. **Configure your virtual host**:
   ```apache
   <VirtualHost *:80>
       ServerName slides.example.com
       DocumentRoot /var/www/slides
       
       <Directory /var/www/slides>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

3. **.htaccess** is included in the project for URL rewriting.

### Nginx

1. **Configure Nginx**:
   ```nginx
   server {
       listen 80;
       server_name slides.example.com;
       root /var/www/slides;
       index index.php;

       location / {
           if (!-f $request_filename) {
               rewrite ^(.*)$ /index.php last;
           }
       }

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```

2. **Remove .htaccess** (not needed for Nginx).

### Docker

Create a `Dockerfile`:
```dockerfile
FROM php:8.0-apache

RUN a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/slideshows && \
    chmod 755 /var/www/html/slideshows

EXPOSE 80
```

Build and run:
```bash
docker build -t slides .
docker run -p 80:80 -v slideshows_volume:/var/www/html/slideshows slides
```

## Usage

### Home Page
- Visit `/` to see all slideshows
- **Play**: View a slideshow in presentation mode
- **Edit**: Modify slideshow content (requires password)
- **Delete**: Remove a slideshow (requires password)

### Creating a Slideshow
1. Click "New Slideshow" on the home page
2. Enter a title
3. You'll be redirected to the editor (password required)
4. Add slides with image URLs and text
5. Click "Save" to store your slideshow

### Editing a Slideshow
1. Click "Edit" next to a slideshow
2. Enter the admin password
3. Select slides from the left panel
4. Modify image URL and text
5. Add or delete slides
6. Click "Save" to update

### Playing a Slideshow
1. Click "Play" or navigate to `/play/slideshow-name`
2. Use arrow keys or buttons to navigate
3. No password required

## Slide Format

Slideshows are stored as JSON in the `slideshows/` directory:

```json
{
  "title": "My Slideshow",
  "slides": [
    {
      "image": "https://example.com/image1.jpg",
      "text": "Slide 1 text\nWith line breaks"
    },
    {
      "image": "https://example.com/image2.jpg",
      "text": "Slide 2 text"
    }
  ]
}
```

## Security

- **Password Protection**: Creation and editing require authentication. Set a strong password in `index.php`.
- **Input Validation**: All user input is sanitized and escaped.
- **Public Play**: Anyone can view slideshows without authentication.
- **HTTPS**: Use HTTPS in production (configure via your web server).

## Troubleshooting

### Slideshows not saving
- Check that the `slideshows/` directory exists and is writable
- Verify PHP has write permissions: `chmod 755 slideshows/`

### URLs not rewriting properly
- For Apache: Ensure `mod_rewrite` is enabled and `.htaccess` is in the root
- For Nginx: Check your server configuration for the rewrite rules

### Password not working
- Verify `config.php` exists and contains a valid `admin_password` entry
- Check that SESSION support is enabled in PHP
- Ensure `config.php` is not excluded from your deployment

## License

**Copyright (C) 2025 Josh at WLTechBlog**

This program is free software; you can redistribute it and/or modify it under the terms of the **GNU General Public License 2.0** as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but **without any warranty**. See the [LICENSE](LICENSE) file for the full text.

For more information, visit: https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
