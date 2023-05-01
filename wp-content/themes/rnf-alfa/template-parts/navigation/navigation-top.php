<?php
/**
 * Displays top navigation
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since Twenty Seventeen 1.0
 * @version 1.2
 */

?>
<nav id="site-navigation" class="main-navigation" role="navigation" aria-label="<?php esc_attr_e( 'Top Menu', 'twentyseventeen' ); ?>">
	<div class="menu-mobile-buttons">
		<button class="map-toggle">
			<?php
			echo twentyseventeen_get_svg( array( 'icon' => 'map' ) );
			?>Map
		</button>
		<button class="menu-toggle" aria-controls="top-menu" aria-expanded="false">
			<?php
			echo twentyseventeen_get_svg( array( 'icon' => 'bars' ) );
			echo twentyseventeen_get_svg( array( 'icon' => 'close' ) );
			_e( 'Menu', 'twentyseventeen' );
			?>
		</button>
	</div>

	<?php
	wp_nav_menu(
		array(
			'theme_location' => 'top',
			'menu_id'        => 'top-menu',
		)
	);
	?>
</nav><!-- #site-navigation -->
