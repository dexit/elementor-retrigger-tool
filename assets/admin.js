/**
 * Admin JavaScript for Elementor Re-Trigger Tool
 *
 * Handles:
 * - CodeMirror integration for JSON viewing/editing
 * - Enhanced modals with tabbed interfaces
 * - Request/Response viewing
 * - Import/Export functionality
 *
 * @package ElementorRetriggerTool
 */

(function($) {
	'use strict';

	/**
	 * CodeMirror Editor Manager
	 */
	var CodeEditorManager = {
		editors: {},

		/**
		 * Initialize CodeMirror editor
		 *
		 * @param {string} elementId DOM element ID
		 * @param {object} options Editor options
		 * @return {object} CodeMirror instance
		 */
		init: function(elementId, options) {
			var element = document.getElementById(elementId);
			if (!element) return null;

			var settings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
			settings.codemirror = _.extend({}, settings.codemirror, {
				mode: 'application/json',
				lineNumbers: true,
				lineWrapping: true,
				readOnly: options.readOnly || false,
				theme: 'default',
				autoRefresh: true,
				viewportMargin: Infinity
			}, options.codemirror || {});

			var editor = wp.codeEditor.initialize(element, settings);
			this.editors[elementId] = editor.codemirror;

			return editor.codemirror;
		},

		/**
		 * Get existing editor instance
		 *
		 * @param {string} elementId DOM element ID
		 * @return {object|null} CodeMirror instance or null
		 */
		get: function(elementId) {
			return this.editors[elementId] || null;
		},

		/**
		 * Set editor value
		 *
		 * @param {string} elementId DOM element ID
		 * @param {string} value New value
		 */
		setValue: function(elementId, value) {
			var editor = this.get(elementId);
			if (editor) {
				editor.setValue(value);
				editor.refresh();
			}
		},

		/**
		 * Format JSON in editor
		 *
		 * @param {string} elementId DOM element ID
		 */
		formatJSON: function(elementId) {
			var editor = this.get(elementId);
			if (editor) {
				try {
					var json = JSON.parse(editor.getValue());
					editor.setValue(JSON.stringify(json, null, 2));
				} catch (e) {
					console.error('Invalid JSON:', e);
				}
			}
		}
	};

	/**
	 * Enhanced Modal Manager
	 */
	var ModalManager = {
		/**
		 * Show modal with CodeMirror editor
		 *
		 * @param {string} modalId Modal DOM ID
		 * @param {object} data Data to display
		 */
		showCodeModal: function(modalId, data) {
			var $modal = $('#' + modalId);
			if (!$modal.length) return;

			// Initialize tabs if they exist
			$modal.find('.e-retrigger-tab').off('click').on('click', function() {
				var $tab = $(this);
				var target = $tab.data('target');

				$tab.siblings().removeClass('active');
				$tab.addClass('active');

				$modal.find('.e-retrigger-tab-content').removeClass('active');
				$('#' + target).addClass('active');

				// Refresh CodeMirror instances
				setTimeout(function() {
					Object.keys(CodeEditorManager.editors).forEach(function(key) {
						CodeEditorManager.editors[key].refresh();
					});
				}, 100);
			});

			$modal.show();

			// Auto-activate first tab
			$modal.find('.e-retrigger-tab:first').trigger('click');
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		console.log('Elementor Re-Trigger Admin JS loaded');

		/**
		 * Tab switching
		 */
		$(document).on('click', '.e-retrigger-tab', function() {
			var $tab = $(this);
			var target = $tab.data('target');

			$tab.siblings().removeClass('active');
			$tab.addClass('active');

			$('.e-retrigger-tab-content').removeClass('active');
			$('#' + target).addClass('active');

			// Refresh all CodeMirror instances
			setTimeout(function() {
				Object.keys(CodeEditorManager.editors).forEach(function(key) {
					if (CodeEditorManager.editors[key]) {
						CodeEditorManager.editors[key].refresh();
					}
				});
			}, 100);
		});

		/**
		 * Format JSON button
		 */
		$(document).on('click', '.format-json-btn', function() {
			var editorId = $(this).data('editor');
			CodeEditorManager.formatJSON(editorId);
		});

		/**
		 * Copy to clipboard
		 */
		$(document).on('click', '.copy-to-clipboard-btn', function() {
			var editorId = $(this).data('editor');
			var editor = CodeEditorManager.get(editorId);
			if (editor) {
				var text = editor.getValue();
				navigator.clipboard.writeText(text).then(function() {
					alert('Copied to clipboard!');
				});
			}
		});
	});

	// Expose managers globally
	window.eRetriggerCodeEditor = CodeEditorManager;
	window.eRetriggerModal = ModalManager;

})(jQuery);
