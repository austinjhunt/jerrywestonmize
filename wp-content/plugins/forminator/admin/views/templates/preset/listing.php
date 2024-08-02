<li>
	<div class="sui-box-selector forminator-card">
		<div class="forminator-card-image">
		<?php if ( ! empty( $thumbnail ) ) { ?>
			<img src="<?php echo esc_url( $thumbnail ) ?>"
				alt=""
				class="sui-image sui-image-center fui-image">
		<?php } else { ?>
			<span class="sui-icon-page" aria-hidden="true"></span>
		<?php } ?>
		</div>
        <?php if ( ! FORMINATOR_PRO && $pro ) { ?>
		    <span class="sui-tag sui-tag-pro"><?php esc_html_e( 'Pro', 'forminator' ); ?></span>
        <?php } ?>
		<div class="forminator-card-details">
			<h3><?php echo esc_html( $name ); ?></h3>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="forminator-card-cta">
				<?php if ( ! empty( $screenshot ) ) { ?>
				<div>
					<button
							class="sui-button sui-button-ghost forminator-template-preview"
							data-modal-open="forminator-modal-template-preview"
							data-screenshot="<?php echo esc_url( $screenshot ); ?>"
							data-title="<?php echo esc_attr( $name ); ?>"
					>
						<?php esc_html_e( 'Preview', 'forminator' ); ?>
					</button>
				</div>
				<?php } ?>
				<div>
					<?php if ( ! FORMINATOR_PRO && $pro ) { ?>
						<a
							class="sui-button sui-button-purple"
							target="_blank"
							href="<?php echo esc_url( 'https://wpmudev.com/project/forminator-pro/?utm_source=forminator&utm_medium=plugin&utm_campaign=forminator_template-page_preset-template-modal&utm_content=' . str_replace( ' ', '-', strtolower( $name ) ) . '-upgrade' ); ?>"
						>
							<?php esc_html_e( 'Upgrade', 'forminator' ); ?>
						</a>
					<?php } else if ( $pro && ! class_exists( 'WPMUDEV_Dashboard' ) ) { ?>
						<a
							class="sui-button sui-button-blue"
							target="_blank"
							href="https://wpmudev.com/project/wpmu-dev-dashboard/"
						>
							<?php esc_html_e( 'Install Plugin', 'forminator' ); ?>
						</a>
					<?php } else if ( $pro && ! WPMUDEV_Dashboard::$api->get_key() ) { ?>
						<a
							class="sui-button sui-button-blue"
							target="_blank"
							href="<?php echo esc_url( network_admin_url( 'admin.php?page=wpmudev' ) ); ?>"
						>
							<?php esc_html_e( 'Log in to use template', 'forminator' ); ?>
						</a>
					<?php } else if ( $pro && 'expired' === forminator_get_wpmudev_membership() ) { ?>
						<a
							class="sui-button sui-button-purple"
							target="_blank"
							href="https://wpmudev.com/project/forminator-pro/?utm_source=forminator&utm_medium=plugin&utm_campaign=forminator_template-page_preset-template_renew"
						>
							<?php esc_html_e( 'Renew Membership', 'forminator' ); ?>
						</a>
					<?php } else { ?>
						<button class="sui-button create-form sui-button-blue" data-id="<?php echo esc_html( $id ); ?>">
							<span class="sui-loading-text">
								<?php esc_html_e( 'Create Form', 'forminator' ); ?>
							</span>
							<!-- Spinning loading icon -->
							<span class="sui-icon-loader sui-loading" aria-hidden="true"></span>
						</button>
					<?php } ?>
				</div>
			</div>
		</div>
	</div>
</li>
