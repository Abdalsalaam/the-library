/**
 * Frontend JavaScript for The Library
 */

(
	function ( $ ) {
		'use strict'

		var WPRL_Frontend = {

			init: function () {
				this.bindEvents()
				this.initFilters()
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

				// Clear search functionality
				$( document ).on( 'click', '.wprl-search-clear', function ( e ) {
					e.preventDefault()
					$( '#wprl-search-input' ).val( '' ).focus()
					WPRL_Frontend.performSearch()
				} )

				// Filter changes - auto-submit on change
				$( document ).on( 'change', '#wprl-category-filter, #wprl-file-type-filter, #wprl-sort-filter', function () {
					WPRL_Frontend.applyFilters()
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

			// Real-time field validation based on dynamic configuration
			$( document ).on( 'input', 'input[data-field-type]', function () {
				var $this = $( this )
				var fieldType = $this.data( 'field-type' )
				var value = $this.val()

				// Handle mobile number cleaning
				if ( fieldType === 'phone' ) {
					// Remove any characters that aren't allowed
					var cleaned = value.replace( /[^\d\s\-\+\(\)]/g, '' )
					if ( cleaned !== value ) {
						$this.val( cleaned )
						value = cleaned
					}
				}

				// Validate field based on type and configuration
				WPRL_Frontend.validateField( $this, fieldType, value )
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

				if ( urlParams.has( 'wprl_file_type' ) ) {
					$( '#wprl-file-type-filter' ).val( urlParams.get( 'wprl_file_type' ) )
				}

				if ( urlParams.has( 'wprl_sort' ) ) {
					$( '#wprl-sort-filter' ).val( urlParams.get( 'wprl_sort' ) )
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
				var fileType = $( '#wprl-file-type-filter' ).val()
				var sort = $( '#wprl-sort-filter' ).val()
				var currentUrl = new URL( window.location.href )

				if ( category ) {
					currentUrl.searchParams.set( 'wprl_category', category )
				} else {
					currentUrl.searchParams.delete( 'wprl_category' )
				}

				if ( fileType ) {
					currentUrl.searchParams.set( 'wprl_file_type', fileType )
				} else {
					currentUrl.searchParams.delete( 'wprl_file_type' )
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

			submitDownloadForm: function ( $form ) {
				var $submitBtn = $form.find( 'button[type="submit"]' )
				var $messageDiv = $( '#wprl-download-message' )
				var originalBtnText = $submitBtn.html()

				// Validate form using dynamic validation rules
				var validationResult = WPRL_Frontend.validateForm( $form )
				if ( !validationResult.isValid ) {
					WPRL_Frontend.showMessage( validationResult.message, 'error', $messageDiv )
					return
				}

				// Show loading state
				$submitBtn.html( '<span class="wprl-spinner"></span> Processing...' ).prop( 'disabled', true )
				$messageDiv.hide()

				// Prepare form data dynamically
				var formData = {
					action: 'wprl_submit_download_form',
					nonce: $form.find( '#wprl_download_nonce' ).val(),
					post_id: $form.find( 'input[name="post_id"]' ).val()
				}

				// Add enabled field values to form data
				$form.find( 'input[data-field-type]' ).each( function () {
					var $field = $( this )
					var fieldName = $field.attr( 'name' )
					var fieldValue = $field.val()
					if ( fieldName && fieldValue !== undefined ) {
						formData[fieldName] = fieldValue
					}
				} )

				$.ajax( {
					url: wprl_ajax.ajax_url,
					type: 'POST',
					data: formData,
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
							// Start download immediately (no success message needed)
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
			},

			// Validate individual field based on type and configuration
			validateField: function ( $field, fieldType, value ) {
				var isValid = true
				var validationRules = wprl_ajax.form_validation || {}
				var fieldName = $field.attr( 'name' )
				var fieldConfig = validationRules[fieldName]

				if ( !fieldConfig || !fieldConfig.enabled ) {
					return true
				}

				// Check if required field is empty
				if ( fieldConfig.required && !value.trim() ) {
					isValid = false
				}

				// Type-specific validation
				if ( value.trim() && fieldType === 'email' && !this.isValidEmail( value ) ) {
					isValid = false
				}

				if ( value.trim() && fieldType === 'phone' && !this.isValidMobile( value ) ) {
					isValid = false
				}

				// Update field visual state
				if ( isValid ) {
					$field.removeClass( 'error' )
				} else {
					$field.addClass( 'error' )
				}

				return isValid
			},

			// Validate entire form using dynamic rules
			validateForm: function ( $form ) {
				var isValid = true
				var errors = []
				var validationRules = wprl_ajax.form_validation || {}

				// Validate each enabled field
				$form.find( 'input[data-field-type]' ).each( function () {
					var $field = $( this )
					var fieldType = $field.data( 'field-type' )
					var fieldName = $field.attr( 'name' )
					var value = $field.val()
					var fieldConfig = validationRules[fieldName]

					if ( !fieldConfig || !fieldConfig.enabled ) {
						return true
					}

					// Check required fields
					if ( fieldConfig.required && !value.trim() ) {
						isValid = false
						$field.addClass( 'error' )

						// Add specific error message based on field type
						if ( fieldType === 'name' ) {
							errors.push( 'Name is required.' )
						} else if ( fieldType === 'email' ) {
							errors.push( 'Email is required.' )
						} else if ( fieldType === 'phone' ) {
							errors.push( 'Phone number is required.' )
						}
					} else {
						$field.removeClass( 'error' )
					}

					// Type-specific validation for non-empty fields
					if ( value.trim() ) {
						if ( fieldType === 'email' && !WPRL_Frontend.isValidEmail( value ) ) {
							isValid = false
							$field.addClass( 'error' )
							errors.push( 'Please enter a valid email address.' )
						}

						if ( fieldType === 'phone' && !WPRL_Frontend.isValidMobile( value ) ) {
							isValid = false
							$field.addClass( 'error' )
							errors.push( 'Please enter a valid mobile number (minimum 7 digits).' )
						}
					}
				} )

				return {
					isValid: isValid,
					message: errors.length > 0 ? errors[0] : 'Please fill in all required fields.'
				}
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
