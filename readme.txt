=== The Library ===
Contributors: abdalsalaam
Tags: file library, downloads, The Library
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A comprehensive WordPress plugin for creating a files/books/videos library with user data collection for downloads.

== Description ==

The Library is a powerful WordPress plugin that allows you to create a comprehensive files library with advanced user data collection features. Perfect for businesses, educational institutions, and content creators who want to track and manage file downloads.

= Admin Features =
* **Custom Post Type**: "Files Library" with file upload capability
* **File Management**: Upload files directly within the post editor
* **Categories**: Organize files with custom taxonomy
* **Download Tracking**: Track all download requests with user data
* **CSV Export**: Export collected user data as CSV
* **Admin Dashboard**: View and manage download requests
* **File Statistics**: View download counts and file details

= Frontend Features =
* **Library Archive**: Main library page similar to blog archive
* **Search & Filters**: Search files and filter by category, file type, and sort options
* **Responsive Design**: Mobile-friendly interface
* **Download Protection**: Users must provide contact information to download
* **User Data Collection**: Collect name, email, and mobile number before download
* **Download Tokens**: Secure download links valid for 24 hours

= Security Features =
* **Nonce Protection**: All AJAX requests are protected with nonces
* **Data Validation**: Server-side validation of all user inputs
* **Download Tokens**: 24-hour tokens for secure downloads with multiple use
* **Rate Limiting**: Prevent multiple requests from same user within 24 hours
* **IP Tracking**: Track IP addresses for download requests

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create the necessary database table
4. Start adding files through the "Files Library" menu in admin

== Usage ==

= Adding Files =
1. Go to **Files Library > Add New** in your WordPress admin
2. Add a title and description for your file
3. Set a featured image (recommended)
4. Use the "File Upload" meta box to upload your file
5. Assign categories if needed
6. Publish the post

= Managing Downloads =
1. Go to **Files Library > Download Requests** to view all download requests
2. Use the search and filter options to find specific requests
3. Export data as CSV using the "Export CSV" button
4. Delete individual requests or use bulk actions

== Customization ==

= Template Override =

You can override the plugin templates by copying them to your theme:

1. Copy `templates/archive-files-library.php` to your theme root
2. Copy `templates/single-files-library.php` to your theme root
3. Customize as needed

== Frequently Asked Questions ==

= Can I customize the download form fields? =
Currently, the plugin collects name, email (optional), and mobile number. Future versions will include customizable fields.

= Are downloads secure? =
Yes, the plugin uses secure download tokens that expire after 24 hours and includes nonce protection for all requests.

= Can I export user data? =
Yes, you can export all collected user data as CSV from the admin dashboard.

== Screenshots ==

1. Files Library archive page with search and filters
2. Single file page with download form
3. Admin dashboard showing download requests
4. File upload interface in admin
5. CSV export functionality

== Changelog ==

= 1.0.0 =
* Security fix.

= 1.0.0 =
* Initial release
* Custom post type for files library
* User data collection for downloads
* Admin dashboard with statistics
* CSV export functionality
* Secure download tokens
* Search and filter functionality