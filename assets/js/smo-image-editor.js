/**
 * SMO Image Editor
 * Handles image manipulation: Crop, Rotate, Flip
 */

(function($) {
    'use strict';

    class SMOImageEditor {
        constructor() {
            this.modal = null;
            this.canvas = null;
            this.ctx = null;
            this.image = null;
            this.originalImage = null;
            this.imageId = null;
            this.filename = '';
            
            this.state = {
                rotation: 0,
                flipH: 1,
                flipV: 1,
                scale: 1,
                isCropping: false,
                cropRect: { x: 0, y: 0, w: 0, h: 0 }
            };

            this.history = [];
            this.historyIndex = -1;

            this.init();
        }

        init() {
            this.createModal();
            this.bindEvents();
        }

        createModal() {
            const modalHtml = `
                <div id="smo-image-editor-modal" class="smo-image-editor-modal">
                    <div class="smo-image-editor-content">
                        <div class="smo-editor-header">
                            <h3 class="smo-editor-title">Image Editor</h3>
                            <button type="button" class="smo-editor-close" title="Close">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <div class="smo-editor-canvas-container">
                            <canvas id="smo-image-canvas"></canvas>
                            <div id="smo-crop-overlay" class="smo-crop-overlay">
                                <div class="smo-crop-handle nw" data-handle="nw"></div>
                                <div class="smo-crop-handle ne" data-handle="ne"></div>
                                <div class="smo-crop-handle sw" data-handle="sw"></div>
                                <div class="smo-crop-handle se" data-handle="se"></div>
                            </div>
                            <div id="smo-editor-loading" class="smo-editor-loading" style="display: none;">
                                <div class="smo-spinner"></div>
                            </div>
                        </div>
                        <div class="smo-editor-toolbar">
                            <div class="smo-editor-tools">
                                <button type="button" class="smo-tool-btn" data-action="rotate-left" title="Rotate Left">
                                    <span class="dashicons dashicons-undo"></span>
                                    <span>Rotate L</span>
                                </button>
                                <button type="button" class="smo-tool-btn" data-action="rotate-right" title="Rotate Right">
                                    <span class="dashicons dashicons-redo"></span>
                                    <span>Rotate R</span>
                                </button>
                                <button type="button" class="smo-tool-btn" data-action="flip-h" title="Flip Horizontal">
                                    <span class="dashicons dashicons-image-flip-horizontal"></span>
                                    <span>Flip H</span>
                                </button>
                                <button type="button" class="smo-tool-btn" data-action="flip-v" title="Flip Vertical">
                                    <span class="dashicons dashicons-image-flip-vertical"></span>
                                    <span>Flip V</span>
                                </button>
                                <button type="button" class="smo-tool-btn" data-action="crop" title="Crop">
                                    <span class="dashicons dashicons-crop"></span>
                                    <span>Crop</span>
                                </button>
                                <button type="button" class="smo-tool-btn" data-action="reset" title="Reset">
                                    <span class="dashicons dashicons-backup"></span>
                                    <span>Reset</span>
                                </button>
                            </div>
                            <div class="smo-editor-actions">
                                <button type="button" class="smo-action-btn smo-btn-cancel">Cancel</button>
                                <button type="button" class="smo-action-btn smo-btn-save">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            this.modal = $('#smo-image-editor-modal');
            this.canvas = document.getElementById('smo-image-canvas');
            this.ctx = this.canvas.getContext('2d');
        }

        bindEvents() {
            // Modal controls
            this.modal.on('click', '.smo-editor-close, .smo-btn-cancel', () => this.close());
            this.modal.on('click', '.smo-btn-save', () => this.save());

            // Tools
            this.modal.on('click', '[data-action]', (e) => {
                const action = $(e.currentTarget).data('action');
                this.handleAction(action);
            });

            // Crop interaction
            const $overlay = $('#smo-crop-overlay');
            let isDragging = false;
            let isResizing = false;
            let currentHandle = null;
            let startX, startY, startLeft, startTop, startWidth, startHeight;

            $overlay.on('mousedown', (e) => {
                if ($(e.target).hasClass('smo-crop-handle')) {
                    isResizing = true;
                    currentHandle = $(e.target).data('handle');
                } else {
                    isDragging = true;
                }
                startX = e.clientX;
                startY = e.clientY;
                startLeft = $overlay.position().left;
                startTop = $overlay.position().top;
                startWidth = $overlay.width();
                startHeight = $overlay.height();
                e.preventDefault();
            });

            $(document).on('mousemove', (e) => {
                if (!this.state.isCropping) return;

                const dx = e.clientX - startX;
                const dy = e.clientY - startY;

                if (isDragging) {
                    let newLeft = startLeft + dx;
                    let newTop = startTop + dy;

                    // Bounds check
                    const container = $overlay.parent();
                    const maxLeft = container.width() - $overlay.outerWidth();
                    const maxTop = container.height() - $overlay.outerHeight();

                    newLeft = Math.max(0, Math.min(newLeft, maxLeft));
                    newTop = Math.max(0, Math.min(newTop, maxTop));

                    $overlay.css({ left: newLeft, top: newTop });
                } else if (isResizing) {
                    let newW = startWidth;
                    let newH = startHeight;
                    let newL = startLeft;
                    let newT = startTop;

                    if (currentHandle.includes('e')) newW = startWidth + dx;
                    if (currentHandle.includes('w')) {
                        newW = startWidth - dx;
                        newL = startLeft + dx;
                    }
                    if (currentHandle.includes('s')) newH = startHeight + dy;
                    if (currentHandle.includes('n')) {
                        newH = startHeight - dy;
                        newT = startTop + dy;
                    }

                    // Min size check
                    if (newW > 50 && newH > 50) {
                        $overlay.css({
                            width: newW,
                            height: newH,
                            left: newL,
                            top: newT
                        });
                    }
                }
            });

            $(document).on('mouseup', () => {
                isDragging = false;
                isResizing = false;
                currentHandle = null;
            });
        }

        open(imageUrl, imageId, filename) {
            this.imageId = imageId;
            this.filename = filename || 'edited-image.jpg';
            this.modal.addClass('active').css('display', 'flex');
            this.loadImage(imageUrl);
        }

        close() {
            this.modal.removeClass('active');
            setTimeout(() => {
                this.modal.hide();
                this.reset();
            }, 300);
        }

        loadImage(url) {
            $('#smo-editor-loading').show();
            this.image = new Image();
            this.image.crossOrigin = "anonymous";
            this.image.onload = () => {
                this.originalImage = this.image;
                this.resetState();
                this.render();
                $('#smo-editor-loading').hide();
            };
            this.image.src = url;
        }

        resetState() {
            this.state = {
                rotation: 0,
                flipH: 1,
                flipV: 1,
                scale: 1,
                isCropping: false,
                cropRect: { x: 0, y: 0, w: 0, h: 0 }
            };
            $('#smo-crop-overlay').hide();
            $('[data-action="crop"]').removeClass('active');
        }

        handleAction(action) {
            switch (action) {
                case 'rotate-left':
                    this.state.rotation -= 90;
                    this.render();
                    break;
                case 'rotate-right':
                    this.state.rotation += 90;
                    this.render();
                    break;
                case 'flip-h':
                    this.state.flipH *= -1;
                    this.render();
                    break;
                case 'flip-v':
                    this.state.flipV *= -1;
                    this.render();
                    break;
                case 'crop':
                    this.toggleCrop();
                    break;
                case 'reset':
                    this.resetState();
                    this.render();
                    break;
            }
        }

        toggleCrop() {
            this.state.isCropping = !this.state.isCropping;
            const $btn = $('[data-action="crop"]');
            const $overlay = $('#smo-crop-overlay');

            if (this.state.isCropping) {
                $btn.addClass('active');
                
                // Initialize crop box centered
                const canvasRect = this.canvas.getBoundingClientRect();
                const containerRect = this.canvas.parentElement.getBoundingClientRect();
                
                // Center the overlay over the canvas
                const w = canvasRect.width * 0.8;
                const h = canvasRect.height * 0.8;
                const l = (containerRect.width - w) / 2;
                const t = (containerRect.height - h) / 2;

                $overlay.css({
                    width: w,
                    height: h,
                    left: l,
                    top: t,
                    display: 'block'
                });
            } else {
                // Apply crop
                this.applyCrop();
                $btn.removeClass('active');
                $overlay.hide();
            }
        }

        applyCrop() {
            const $overlay = $('#smo-crop-overlay');
            const canvasRect = this.canvas.getBoundingClientRect();
            const overlayRect = $overlay[0].getBoundingClientRect();

            // Calculate crop coordinates relative to the displayed canvas
            const scaleX = this.canvas.width / canvasRect.width;
            const scaleY = this.canvas.height / canvasRect.height;

            const x = (overlayRect.left - canvasRect.left) * scaleX;
            const y = (overlayRect.top - canvasRect.top) * scaleY;
            const w = overlayRect.width * scaleX;
            const h = overlayRect.height * scaleY;

            // Create a temporary canvas to hold the cropped image
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = w;
            tempCanvas.height = h;
            const tempCtx = tempCanvas.getContext('2d');

            // Draw the cropped portion
            tempCtx.drawImage(this.canvas, x, y, w, h, 0, 0, w, h);

            // Update the main image source to the cropped version
            const croppedImage = new Image();
            croppedImage.onload = () => {
                this.image = croppedImage;
                // Reset transformations as they are now baked into the new image
                this.state.rotation = 0;
                this.state.flipH = 1;
                this.state.flipV = 1;
                this.render();
            };
            croppedImage.src = tempCanvas.toDataURL();
        }

        render() {
            if (!this.image) return;

            // Calculate dimensions based on rotation
            const isRotated = Math.abs(this.state.rotation % 180) === 90;
            const w = isRotated ? this.image.height : this.image.width;
            const h = isRotated ? this.image.width : this.image.height;

            // Resize canvas to fit container while maintaining aspect ratio
            const container = this.canvas.parentElement;
            const maxWidth = container.clientWidth - 40;
            const maxHeight = container.clientHeight - 40;
            
            let scale = Math.min(maxWidth / w, maxHeight / h);
            // Don't upscale small images too much
            if (scale > 1) scale = 1;

            this.canvas.width = w;
            this.canvas.height = h;
            
            // Apply CSS styles for display size
            this.canvas.style.width = `${w * scale}px`;
            this.canvas.style.height = `${h * scale}px`;

            // Draw
            this.ctx.save();
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            
            // Move to center
            this.ctx.translate(this.canvas.width / 2, this.canvas.height / 2);
            
            // Rotate
            this.ctx.rotate(this.state.rotation * Math.PI / 180);
            
            // Flip
            this.ctx.scale(this.state.flipH, this.state.flipV);
            
            // Draw image centered
            this.ctx.drawImage(
                this.image, 
                -this.image.width / 2, 
                -this.image.height / 2
            );
            
            this.ctx.restore();
        }

        save() {
            const $btn = $('.smo-btn-save');
            $btn.prop('disabled', true).text('Saving...');

            const dataUrl = this.canvas.toDataURL('image/jpeg', 0.9);

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_save_edited_image',
                    nonce: smo_social_ajax.nonce,
                    image_id: this.imageId,
                    image_data: dataUrl,
                    filename: this.filename
                }
            })
            .done((response) => {
                if (response.success) {
                    // Refresh parent if possible
                    if (window.SMOMediaLibrary) {
                        window.SMOMediaLibrary.loadMedia();
                    }
                    this.close();
                    // Show success message (using existing notification system if available)
                    alert('Image saved successfully!');
                } else {
                    alert('Failed to save image: ' + response.data);
                }
            })
            .fail(() => {
                alert('Network error occurred while saving.');
            })
            .always(() => {
                $btn.prop('disabled', false).text('Save Changes');
            });
        }
    }

    // Expose to global scope
    window.SMOImageEditor = new SMOImageEditor();

})(jQuery);
