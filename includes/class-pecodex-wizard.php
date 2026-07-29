<?php
/**
 * Setup Wizard for Pecodex Media Control
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pecodex_Wizard {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_wizard_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_wizard_menu() {
        add_submenu_page(
            'options-general.php',
            __( 'Pecodex Setup', 'pecodex-media-control' ),
            __( 'Pecodex Setup', 'pecodex-media-control' ),
            'manage_options',
            'pecodex-setup',
            array( $this, 'render_wizard_page' )
        );
    }

    public function register_settings() {
        register_setting( 'pecodex_setup_group', 'pecodex_security_level' );
    }

    public function render_wizard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $current_level = get_option( 'pecodex_security_level', 'relaxed' );
        ?>
        <div class="wrap pecodex-wizard-wrap">
            <style>
                .pecodex-wizard-wrap {
                    max-width: 800px;
                    margin: 40px auto;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background: #fff;
                    border-radius: 12px;
                    box-shadow: 0 8px 30px rgba(0,0,0,0.05);
                    overflow: hidden;
                }
                .pecodex-wizard-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 40px;
                    text-align: center;
                }
                .pecodex-wizard-header h1 {
                    color: white;
                    margin: 0;
                    font-size: 28px;
                    font-weight: 600;
                }
                .pecodex-wizard-header p {
                    margin-top: 10px;
                    font-size: 16px;
                    opacity: 0.9;
                }
                .pecodex-wizard-content {
                    padding: 40px;
                }
                .pecodex-options-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 24px;
                    margin-top: 10px;
                }
                .pecodex-option-card {
                    border: 2px solid #e2e8f0;
                    border-radius: 10px;
                    padding: 30px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    position: relative;
                    display: block;
                }
                .pecodex-option-card:hover {
                    border-color: #667eea;
                    transform: translateY(-2px);
                    box-shadow: 0 10px 20px rgba(102,126,234,0.1);
                }
                .pecodex-option-card.selected {
                    border-color: #667eea;
                    background: rgba(102,126,234,0.05);
                }
                .pecodex-option-icon {
                    font-size: 32px;
                    margin-bottom: 15px;
                }
                .pecodex-option-title {
                    font-size: 20px;
                    font-weight: 600;
                    margin-bottom: 10px;
                    color: #1a202c;
                }
                .pecodex-option-desc {
                    color: #4a5568;
                    font-size: 14px;
                    line-height: 1.5;
                }
                .pecodex-wizard-footer {
                    padding: 20px 40px;
                    background: #f8fafc;
                    border-top: 1px solid #e2e8f0;
                    text-align: right;
                }
                .pecodex-btn {
                    background: #667eea;
                    color: white;
                    border: none;
                    padding: 12px 24px;
                    border-radius: 6px;
                    font-size: 16px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .pecodex-btn:hover {
                    background: #5a67d8;
                }
                /* Hide default WP notices in wizard */
                .pecodex-wizard-wrap ~ .notice,
                .pecodex-wizard-wrap ~ #setting-error-settings_updated {
                    display: none !important;
                }
            </style>
            
            <div class="pecodex-wizard-header">
                <h1>Pecodex Media Control Setup</h1>
                <p>Choose your preferred security level to get started</p>
            </div>
            
            <form method="post" action="options.php">
                <div class="pecodex-wizard-content">
                    <?php settings_fields( 'pecodex_setup_group' ); ?>
                    
                    <div class="pecodex-options-grid">
                        <label class="pecodex-option-card <?php echo $current_level === 'strict' ? 'selected' : ''; ?>">
                            <div class="pecodex-option-icon">🛡️</div>
                            <div class="pecodex-option-title">Strict Security</div>
                            <div class="pecodex-option-desc">
                                Maximum protection. Media access is tightly controlled, ensuring only highly privileged users can interact with sensitive files. Recommended for e-commerce and private sites.
                            </div>
                            <input type="radio" name="pecodex_security_level" value="strict" style="display: none;" <?php checked( $current_level, 'strict' ); ?>>
                        </label>
                        
                        <label class="pecodex-option-card <?php echo $current_level === 'relaxed' ? 'selected' : ''; ?>">
                            <div class="pecodex-option-icon">✨</div>
                            <div class="pecodex-option-title">Relaxed Security</div>
                            <div class="pecodex-option-desc">
                                Balanced approach. Standard WordPress media behavior with essential protections. Recommended for blogs and standard corporate websites.
                            </div>
                            <input type="radio" name="pecodex_security_level" value="relaxed" style="display: none;" <?php checked( $current_level, 'relaxed' ); ?>>
                        </label>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const cards = document.querySelectorAll('.pecodex-option-card');
                            cards.forEach(card => {
                                card.addEventListener('click', function() {
                                    cards.forEach(c => c.classList.remove('selected'));
                                    this.classList.add('selected');
                                    // Radio button selection is handled automatically by the label element
                                });
                            });
                        });
                    </script>
                </div>
                
                <div class="pecodex-wizard-footer">
                    <button type="submit" class="pecodex-btn">Save Configuration</button>
                </div>
            </form>
        </div>
        <?php
    }
}

new Pecodex_Wizard();
