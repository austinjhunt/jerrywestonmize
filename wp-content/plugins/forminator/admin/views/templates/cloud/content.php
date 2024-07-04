<div
	role="tabpanel"
	tabindex="0"
	id="cloud-templates-content"
	class="sui-tab-content"
	aria-labelledby="cloud-templates"
>
	<!-- Pro saved template box -->
	<div class="sui-box">
		<?php
		if ( ! FORMINATOR_PRO ) {
			echo forminator_template( 'templates/cloud/upgrade-content' );
		} elseif ( ! class_exists( 'WPMUDEV_Dashboard' ) ) {
			echo forminator_template( 'templates/cloud/wpmudev-install' );
		} elseif ( ! WPMUDEV_Dashboard::$api->get_key() ) {
			echo forminator_template( 'templates/cloud/wpmudev-login' );
		} elseif ( 'expired' === forminator_get_wpmudev_membership() ) {
			echo forminator_template( 'templates/cloud/wpmudev-renew' );
		} else {
			echo forminator_template( 'templates/cloud/listings' );
			echo forminator_template( 'templates/cloud/empty-content' );
		}
		?>
	</div>
</div>
