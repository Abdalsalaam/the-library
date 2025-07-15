/**
 * Frontend JavaScript for WP Resource Library
 */

(
	function ( $ ) {
		'use strict'

		var WPRL_Frontend = {

			init: function () {
				this.bindEvents()
				this.initFilters()
				this.initLoadMore()
			},

			bindEvents: function () {
				// Search functionality
				$( document ).on( 'keypress', '#wprl-search-input', function ( e ) {
					if ( e.which === 13 ) {
						e.preventDefault()
						WPRL_Frontend.performSearch()
					}
				} )

				$( document ).on( 'click', '#wprl-search-button', function ( e ) {
					e.preventDefault()
					WPRL_Frontend.performSearch()
				} )

				// Filter changes
				$( document ).on( 'change', '#wprl-category-filter, #wprl-sort-filter', function () {
					WPRL_Frontend.applyFilters()
				} )

				// Load more functionality
				$( document ).on( 'click', '#wprl-load-more', function ( e ) {
					e.preventDefault()
					WPRL_Frontend.loadMoreFiles( $( this ) )
				} )

				// Download form submission
				$( document ).on( 'submit', '#wprl-download-form', function ( e ) {
					e.preventDefault()
					WPRL_Frontend.submitDownloadForm( $( this ) )
				} )

				// Manual download button
				$( document ).on( 'click', '#wprl-manual-download', function ( e ) {
					e.preventDefault()
					WPRL_Frontend.triggerDownload()
				} )

			// Direct download for logged-in users (single page and archive)
			$( document ).on( 'click', '#wprl-direct-download, .wprl-direct-download', function ( e ) {
				e.preventDefault()
				WPRL_Frontend.handleDirectDownload( $( this ) )
			} )

			// Real-time mobile number validation
			$( document ).on( 'input', '#wprl_user_mobile', function () {
				var $this = $( this )
				var mobile = $this.val()

				// Remove any characters that aren't allowed
				var cleaned = mobile.replace( /[^\d\s\-\+\(\)]/g, '' )
				if ( cleaned !== mobile ) {
					$this.val( cleaned )
				}

				// Remove error class if valid
				if ( WPRL_Frontend.isValidMobile( cleaned ) ) {
					$this.removeClass( 'error' )
				}
			} )
			},

			initFilters: function () {
				// Initialize filter state from URL parameters
				var urlParams = new URLSearchParams( window.location.search )

				if ( urlParams.has( 'wprl_search' ) ) {
					$( '#wprl-search-input' ).val( urlParams.get( 'wprl_search' ) )
				}

				if ( urlParams.has( 'wprl_category' ) ) {
					$( '#wprl-category-filter' ).val( urlParams.get( 'wprl_category' ) )
				}

				if ( urlParams.has( 'wprl_sort' ) ) {
					$( '#wprl-sort-filter' ).val( urlParams.get( 'wprl_sort' ) )
				}
			},

			initLoadMore: function () {
				// Initialize load more button state
				var $loadMoreBtn = $( '#wprl-load-more' )
				if ( $loadMoreBtn.length ) {
					var currentPage = parseInt( $loadMoreBtn.data( 'page' ) ) || 1
					var maxPages = parseInt( $loadMoreBtn.data( 'max-pages' ) ) || 1

					if ( currentPage >= maxPages ) {
						$loadMoreBtn.hide()
					}
				}
			},

			performSearch: function () {
				var searchTerm = $( '#wprl-search-input' ).val().trim()
				var currentUrl = new URL( window.location.href )

				if ( searchTerm ) {
					currentUrl.searchParams.set( 'wprl_search', searchTerm )
				} else {
					currentUrl.searchParams.delete( 'wprl_search' )
				}

				// Reset to first page
				currentUrl.searchParams.delete( 'paged' )

				window.location.href = currentUrl.toString()
			},

			applyFilters: function () {
				var category = $( '#wprl-category-filter' ).val()
				var sort = $( '#wprl-sort-filter' ).val()
				var currentUrl = new URL( window.location.href )

				if ( category ) {
					currentUrl.searchParams.set( 'wprl_category', category )
				} else {
					currentUrl.searchParams.delete( 'wprl_category' )
				}

				if ( sort ) {
					currentUrl.searchParams.set( 'wprl_sort', sort )
				} else {
					currentUrl.searchParams.delete( 'wprl_sort' )
				}

				// Reset to first page
				currentUrl.searchParams.delete( 'paged' )

				window.location.href = currentUrl.toString()
			},

			loadMoreFiles: function ( $button ) {
				var currentPage = parseInt( $button.data( 'page' ) ) || 1
				var maxPages = parseInt( $button.data( 'max-pages' ) ) || 1
				var nextPage = currentPage + 1

				if ( nextPage > maxPages ) {
					$button.hide()
					return
				}

				// Show loading state
				var originalText = $button.text()
				$button.text( wprl_ajax.loading_text ).prop( 'disabled', true )

				// Get current filters
				var searchTerm = $( '#wprl-search-input' ).val() || ''
				var category = $( '#wprl-category-filter' ).val() || ''
				var sort = $( '#wprl-sort-filter' ).val() || ''

				$.ajax( {
					url: wprl_ajax.ajax_url,
					type: 'POST',
					data: {
						action: 'wprl_load_more_files',
						nonce: wprl_ajax.nonce,
						page: nextPage,
						search: searchTerm,
						category: category,
						sort: sort
					},
					success: function ( response ) {
						if ( response.success && response.data.html ) {
							$( '.wprl-files-grid' ).append( response.data.html )
							$button.data( 'page', nextPage )

							if ( !response.data.has_more || nextPage >= maxPages ) {
								$button.hide()
							}

							// Trigger custom event for loaded content
							$( document ).trigger( 'wprl:files_loaded', [response.data.html] )
						} else {
							WPRL_Frontend.showMessage( wprl_ajax.error_message, 'error' )
						}
					},
					error: function () {
						WPRL_Frontend.showMessage( wprl_ajax.error_message, 'error' )
					},
					complete: function () {
						$button.text( originalText ).prop( 'disabled', false )
					}
				} )
			},

			submitDownloadForm: function ( $form ) {
				var $submitBtn = $form.find( 'button[type="submit"]' )
				var $messageDiv = $( '#wprl-download-message' )
				var originalBtnText = $submitBtn.html()

				// Validate form
				var isValid = true
				$form.find( 'input[required]' ).each( function () {
					if ( !$( this ).val().trim() ) {
						isValid = false
						$( this ).addClass( 'error' )
					} else {
						$( this ).removeClass( 'error' )
					}
				} )

				if ( !isValid ) {
					WPRL_Frontend.showMessage( 'Please fill in all required fields.', 'error', $messageDiv )
					return
				}

				// Validate mobile number
				var mobile = $( '#wprl_user_mobile' ).val().trim()
				if ( mobile && !WPRL_Frontend.isValidMobile( mobile ) ) {
					WPRL_Frontend.showMessage( 'Please enter a valid mobile number (minimum 7 digits).', 'error', $messageDiv )
					$( '#wprl_user_mobile' ).addClass( 'error' )
					return
				}

				// Validate email if provided
				var email = $( '#wprl_user_email' ).val().trim()
				if ( email && !WPRL_Frontend.isValidEmail( email ) ) {
					WPRL_Frontend.showMessage( 'Please enter a valid email address.', 'error', $messageDiv )
					$( '#wprl_user_email' ).addClass( 'error' )
					return
				}

				// Show loading state
				$submitBtn.html( '<span class="wprl-spinner"></span> Processing...' ).prop( 'disabled', true )
				$messageDiv.hide()

				$.ajax( {
					url: wprl_ajax.ajax_url,
					type: 'POST',
					data: {
						action: 'wprl_submit_download_form',
						nonce: $form.find( '#wprl_download_nonce' ).val(),
						post_id: $form.find( 'input[name="post_id"]' ).val(),
						user_name: $( '#wprl_user_name' ).val(),
						user_email: $( '#wprl_user_email' ).val(),
						user_mobile: $( '#wprl_user_mobile' ).val()
					},
					success: function ( response ) {
						if ( response.success ) {
							// Hide form and show success message
							$( '#wprl-download-form-container' ).fadeOut( function () {
								$( '#wprl-download-success' ).fadeIn()
							} )

							// Store download token and URL
							WPRL_Frontend.downloadToken = response.data.download_token
							WPRL_Frontend.downloadUrl = response.data.download_url

							// Auto-start download after 2 seconds
							setTimeout( function () {
								WPRL_Frontend.triggerDownload()
							}, 2000 )

						} else {
							WPRL_Frontend.showMessage( response.data.message, 'error', $messageDiv )
						}
					},
					error: function () {
						WPRL_Frontend.showMessage( 'An error occurred. Please try again.', 'error', $messageDiv )
					},
					complete: function () {
						$submitBtn.html( originalBtnText ).prop( 'disabled', false )
					}
				} )
			},

			triggerDownload: function () {
				if ( WPRL_Frontend.downloadUrl ) {
					// Directly use the download URL
					window.location.href = WPRL_Frontend.downloadUrl
				} else if ( WPRL_Frontend.downloadToken ) {
					// Fallback: construct URL from token (for backward compatibility)
					var postId = $( 'input[name="post_id"]' ).val()
					var downloadUrl = window.location.origin + '/?wprl_download=1&token=' +
					                  WPRL_Frontend.downloadToken + '&post_id=' + postId
					window.location.href = downloadUrl
				}
			},

			handleDirectDownload: function ( $button ) {
				var postId = $button.data( 'post-id' )
				var $messageDiv = $( '#wprl-direct-download-message' )
				var originalText = $button.html()

				// Show loading state
				$button.html( '<span class="wprl-spinner"></span> ' + wprl_ajax.loading_text ).prop( 'disabled', true )

				// Hide message div if it exists
				if ( $messageDiv.length ) {
					$messageDiv.hide()
				}

				$.ajax( {
					url: wprl_ajax.ajax_url,
					type: 'POST',
					data: {
						action: 'wprl_direct_download',
						nonce: wprl_ajax.direct_download_nonce,
						post_id: postId
					},
					success: function ( response ) {
						if ( response.success ) {
							// Show success message if message div exists
							if ( $messageDiv.length ) {
								$messageDiv.removeClass( 'wprl-error' ).addClass( 'wprl-success' )
									.text( response.data.message ).show()
							}

							// Start download immediately
							window.location.href = response.data.download_url
						} else {
							// Show error message
							if ( $messageDiv.length ) {
								$messageDiv.removeClass( 'wprl-success' ).addClass( 'wprl-error' )
									.text( response.data.message ).show()
							} else {
								// Fallback: show alert for archive page
								alert( response.data.message )
							}
						}
					},
					error: function () {
						// Show error message
						if ( $messageDiv.length ) {
							$messageDiv.removeClass( 'wprl-success' ).addClass( 'wprl-error' )
								.text( wprl_ajax.error_message ).show()
						} else {
							// Fallback: show alert for archive page
							alert( wprl_ajax.error_message )
						}
					},
					complete: function () {
						$button.html( originalText ).prop( 'disabled', false )
					}
				} )
			},

			showMessage: function ( message, type, $container ) {
				if ( !$container ) {
					$container = $( '#wprl-download-message' )
				}

				$container.removeClass( 'wprl-success wprl-error wprl-warning wprl-info' )
				          .addClass( 'wprl-' + type )
				          .text( message )
				          .fadeIn()

				// Auto-hide success messages after 5 seconds
				if ( type === 'success' ) {
					setTimeout( function () {
						$container.fadeOut()
					}, 5000 )
				}
			},

			isValidEmail: function ( email ) {
				var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
				return emailRegex.test( email )
			},

			isValidMobile: function ( mobile ) {
				// Check if it contains only allowed characters
				var allowedCharsRegex = /^[\d\s\-\+\(\)]+$/
				if ( !allowedCharsRegex.test( mobile ) ) {
					return false
				}

				// Extract only digits for length validation
				var digitsOnly = mobile.replace( /[^\d]/g, '' )

				// Check minimum length (at least 7 digits)
				if ( digitsOnly.length < 7 ) {
					return false
				}

				// Check maximum length (no more than 15 digits - international standard)
				if ( digitsOnly.length > 15 ) {
					return false
				}

				return true
			},

			// Utility function to get URL parameter
			getUrlParameter: function ( name ) {
				var urlParams = new URLSearchParams( window.location.search )
				return urlParams.get( name )
			},

			// Utility function to update URL parameter
			updateUrlParameter: function ( key, value ) {
				var url = new URL( window.location.href )
				if ( value ) {
					url.searchParams.set( key, value )
				} else {
					url.searchParams.delete( key )
				}
				return url.toString()
			}
		}

		// Initialize when document is ready
		$( document ).ready( function () {
			WPRL_Frontend.init()
		} )

		// Make WPRL_Frontend globally accessible
		window.WPRL_Frontend = WPRL_Frontend

	}
)( jQuery )
