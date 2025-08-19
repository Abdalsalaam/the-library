![cover.jpg](cover.jpg)
# The Library WordPress Plugin

A comprehensive WordPress plugin for creating a files/books/videos library with user data collection for downloads.

## Features

### Admin Features
- **Custom Post Type**: "Files Library" with file upload capability
- **File Management**: Upload files directly within the post editor
- **Categories**: Organize files with custom taxonomy
- **Download Tracking**: Track all download requests with user data
- **CSV Export**: Export collected user data as CSV
- **Admin Dashboard**: View and manage download requests
- **File Statistics**: View download counts and file details

### Frontend Features
- **Library Archive**: Main library page similar to blog archive
- **Search & Filters**: Search files and filter by category, file type, and sort options
- **Responsive Design**: Mobile-friendly interface
- **Download Protection**: Users must provide contact information to download
- **User Data Collection**: Collect name, email, and mobile number before download
- **Download Tokens**: Secure download links valid for 24 hours

### Security Features
- **Nonce Protection**: All AJAX requests are protected with nonces
- **Data Validation**: Server-side validation of all user inputs
- **Download Tokens**: 24-hour tokens for secure downloads with multiple use
- **Rate Limiting**: Prevent multiple requests from same user within 24 hours
- **IP Tracking**: Track IP addresses for download requests

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create the necessary database table
4. Start adding files through the "Files Library" menu in admin

## Usage

### Adding Files
1. Go to **Files Library > Add New** in your WordPress admin
2. Add a title and description for your file
3. Set a featured image (recommended)
4. Use the "File Upload" meta box to upload your file
5. Assign categories if needed
6. Publish the post

### Managing Downloads
1. Go to **Files Library > Download Requests** to view all download requests
2. Use the search and filter options to find specific requests
3. Export data as CSV using the "Export CSV" button
4. Delete individual requests or use bulk actions

## Customization

### Template Override

You can override the plugin templates by copying them to your theme:

1. Copy `templates/archive-files-library.php` to your theme root
2. Copy `templates/single-files-library.php` to your theme root
3. Customize as needed
