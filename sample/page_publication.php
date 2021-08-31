<?php
/**
 * The Template for Publication Static Pages
 *
 * Template Name: Publications
 *
 * @package Sample
 * @author Takuto Yanagida
 * @version 2021-08-31
 */

get_header();
?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

<?php
while ( have_posts() ) {
	the_post();
	get_template_part( 'template-parts/entry', 'page' );
}
?>
			<section>
				<div class="entry-content">
					<?php \wplug\bimeson_item\the_filter(); ?>
					<?php \wplug\bimeson_item\the_list(); ?>
				</div>
			</section>

		</main>
	</div>
<?php
get_footer();
