/**
 * Script to update the quote confirmation page title
 * based on quote status
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const status = wcInvoiceQuoteConfirmation.quoteStatus;
        const orderId = wcInvoiceQuoteConfirmation.orderId;

        const statusMessages = {
            'quote-draft': {
                title: wcInvoiceQuoteConfirmation.draftTitle,
                message: wcInvoiceQuoteConfirmation.draftMessage
            },
            'quote-request': {
                title: wcInvoiceQuoteConfirmation.requestTitle,
                message: wcInvoiceQuoteConfirmation.requestMessage
            },
            'quote-awaiting': {
                title: wcInvoiceQuoteConfirmation.awaitingTitle,
                message: wcInvoiceQuoteConfirmation.awaitingMessage
            },
            'quote-approved': {
                title: wcInvoiceQuoteConfirmation.approvedTitle,
                message: wcInvoiceQuoteConfirmation.approvedMessage
            },
            'quote-cancelled': {
                title: wcInvoiceQuoteConfirmation.cancelledTitle,
                message: wcInvoiceQuoteConfirmation.cancelledMessage
            },
            'quote-expired': {
                title: wcInvoiceQuoteConfirmation.expiredTitle,
                message: wcInvoiceQuoteConfirmation.expiredMessage
            }
        };

        // Function to update title and message
        function updateQuoteConfirmationStatus() {

            // Add CSS classes to body for styling
            document.body.classList.add('wc-quote-confirmation-custom', 'wc-quote-status-' + status.replace('quote-', ''));

            // Look for WooCommerce status block
            const statusBlock = document.querySelector('.wp-block-woocommerce-order-confirmation-status, .wc-block-order-confirmation-status');
            
            if (statusBlock) {
                const titleElement = statusBlock.querySelector('h1');
                const messageElement = statusBlock.querySelector('p');
                
                if (titleElement) {
                    titleElement.textContent = statusMessages[status].title;
                }
                
                if (messageElement) {
                    messageElement.textContent = statusMessages[status].message;
                }
                
                return true; // Indicates successful update
            }
            
            // Fallback: look for other common title elements
            const fallbackSelectors = [
                '.entry-title',
                '.page-title', 
                'h1.woocommerce-order-received-title',
                '.woocommerce-thankyou-order-received h1',
                '.woocommerce-order h1',
                'h1', // Last resort - first h1 on the page
            ];
            
            
            for (const selector of fallbackSelectors) {
                const element = document.querySelector(selector);
                
                if (element) {
                    // Check if current text seems to be quote/order related
                    const currentText = element.textContent.toLowerCase();
                    if (currentText.includes('orçamento') || 
                        currentText.includes('pedido') || 
                        currentText.includes('recebido') ||
                        currentText.includes('order') ||
                        currentText.includes('received')) {
                        
                        element.textContent = statusMessages[status].title;
                        return true;
                    }
                }
            }
            
            return false;
        }

        // Execute update when page loads
        const success = updateQuoteConfirmationStatus();
        
        if (!success) {
            // Use MutationObserver to capture dynamic DOM changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        // Check if new elements were added
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) { // Element node
                                if (node.matches && 
                                    (node.matches('.wp-block-woocommerce-order-confirmation-status') || 
                                     node.matches('.wc-block-order-confirmation-status'))) {
                                    if (updateQuoteConfirmationStatus()) {
                                        observer.disconnect(); // Stop observing if update succeeded
                                    }
                                }
                            }
                        });
                    }
                });
            });

            // Observe changes in body apenas se existir
            if (document.body) {
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            } else {
                document.addEventListener('DOMContentLoaded', function() {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                });
            }
        }
    });

})(jQuery);
