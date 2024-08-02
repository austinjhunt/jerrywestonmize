<?php
if ( FORMINATOR_PRO ) {
	if ( ! class_exists( 'WPMUDEV_Dashboard' ) ) {
		echo forminator_template( 'templates/banner/wpmudev-install' );
	} elseif ( ! WPMUDEV_Dashboard::$api->get_key() ) {
		echo forminator_template( 'templates/banner/wpmudev-login' );
	} elseif ( 'expired' === forminator_get_wpmudev_membership() ) {
		echo forminator_template( 'templates/banner/wpmudev-expired' );
	}
}
?>

<div id="forminator-templates" class="sui-tabs">

	<div role="tablist" class="sui-tabs-menu">

		<button
			type="button"
			role="tab"
			id="all-templates"
			class="sui-tab-item active"
			aria-controls="all-templates-content"
			aria-selected="true"
		>
			<?php esc_html_e( 'Preset Templates', 'forminator' ); ?>
		</button>
		<?php if ( is_wpmu_dev_admin() || ! forminator_can_whitelabel() ) { ?>
            <button
                type="button"
                role="tab"
                id="cloud-templates"
                class="sui-tab-item"
                aria-controls="cloud-templates-content"
                aria-selected="false"
                tabindex="-1"
            >
                <?php esc_html_e( 'Cloud Templates', 'forminator' ); ?>
                <?php if ( ! FORMINATOR_PRO ) : ?>
                    <span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Pro', 'forminator' ); ?></span>
                <?php endif; ?>
            </button>
        <?php } ?>
	</div>

	<div class="sui-tabs-content">
		<?php echo forminator_template( 'templates/preset/content' );
		if ( is_wpmu_dev_admin() || ! forminator_can_whitelabel() ) {
			echo forminator_template( 'templates/cloud/content' );
		} ?>
	</div>
	<?php echo forminator_template( 'templates/preset/popup' ); ?>
</div>
