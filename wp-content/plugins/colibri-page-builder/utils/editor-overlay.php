<?php

global $post;

?>
<style>
    div#wp-content-editor-container {
        position: relative;
    }

    div#wp-content-editor-container textarea {
        max-height: 500px;
        height: 500px;
    }

    div#wp-content-editor-tools {
        display: none;
    }

    table#post-status-info {
        display: none;
    }

    .cp_customizer-editor-overlay {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: #ececec;
        border: 1px solid #cacaca;

    }

    div#wp-content-wrap {
        margin-top: 1rem;
        margin-bottom: 1rem;
    }

    .cp_customizer-editor-overlay .middle-align {
        position: relative;
        top: 50%;
        transform: translateY(-50%);
        text-align: center;
    }

    .cp_customizer-editor-overlay i.dashicons.dashicons-edit {
        font-size: 1.8em;
        width: auto;
        margin-right: 2px;
        vertical-align: middle;
        height: 30px;
    }

    .cp_customizer-editor-overlay .button.button-link,
    .cp_customizer-editor-overlay .button.button-link:hover,
    .cp_customizer-editor-overlay .button.button-link:focus {
        background: transparent;
        outline: none;
        box-shadow: none;
    }
   .cp_customizer-editor-overlay .button.button-link,
   .cp_customizer-editor-overlay .button.button-link:hover,
   .cp_customizer-editor-overlay .button.button-link:focus {
        background: transparent;
    }


    .cp_customizer-editor-overlay .button {
        border: none;
        font-weight: 400 !important;
    }

    .cp_customizer-editor-overlay .button:active,.cp_customizer-editor-overlay .button:focus,.cp_customizer-editor-overlay .button:hover {
        border: none;
        box-shadow: none!important
    }

    .cp_customizer-editor-overlay {
        --wp-block-synced-color: #7a00df;
        --wp-block-synced-color--rgb: 122,0,223;
        --wp-bound-block-color: var(--wp-block-synced-color);
        --wp-editor-canvas-background: #ddd;
        --wp-admin-theme-color: #007cba;
        --wp-admin-theme-color--rgb: 0,124,186;
        --wp-admin-theme-color-darker-10: #006ba1;
        --wp-admin-theme-color-darker-10--rgb: 0,107,161;
        --wp-admin-theme-color-darker-20: #005a87;
        --wp-admin-theme-color-darker-20--rgb: 0,90,135
    }

    .cp_customizer-editor-overlay:not(#extra-1) .button-link {
        color: #2271b1;
        text-decoration: underline
    }

    .cp_customizer-editor-overlay:not(#extra-1) .button-link:hover {
        border-color: #0a4b78;
        color: #135e96
    }


    .cp_customizer-editor-overlay  .button-primary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .cp_customizer-editor-overlay .button-primary .dashicons {
        line-height: 1 !important;
        aspect-ratio: 1;
        height: auto !important;
    }


</style>

<script>
    window.cp_open_page_in_default_editor = function (event,page) {
        event.preventDefault();
        event.stopPropagation();
        var response = confirm("<?php _e('This post was previously edited in Customizer. You can continue in the Default Editor, but you may lose data and formatting.', 'colibri-page-builder') //phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction ?>");
        if (response) {
            var data = {
                action: 'cp_open_in_default_editor',
                page: page,
                _wpnonce: '<?php echo wp_create_nonce('cp_open_in_default_editor_nonce'); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>'
            };

            jQuery.post(ajaxurl, data).done(function (response) {
                setTimeout(function () {
                    console.log('refresh', window.location.toString() + "&cp_default_editor=" + Date.now());
                    window.location = window.location.toString() + "&cp_default_editor=" + Date.now();
                }, 500);
            });
        }

        return false;
    }
</script>

<div class="colibri-classic-editor-overlay cp_customizer-editor-overlay">
    <div class="middle-align">
        <div>
            <button onclick="cp_open_page_in_customizer(<?php echo esc_attr($post->ID); ?>)" class="button button-hero button-primary">
                <i class="dashicons dashicons-edit"></i>
				<?php _e('Edit in Colibri', 'colibri-page-builder') //phpcs:ignore  WordPress.Security.EscapeOutput.UnsafePrintingFunction ?>
            </button>
        </div>
        <div class="colibri-edit-with-default-wrapper" style="padding-top: 1em;">
            <button onclick="cp_open_page_in_default_editor(event,'<?php echo esc_attr($post->ID); ?>')" class="button button-link"><?php _e('Edit In Default Editor', 'colibri-page-builder') //phpcs:ignore WordPress.Security.EscapeOutput.UnsafePrintingFunction ?></button>
        </div>
    </div>
</div>
