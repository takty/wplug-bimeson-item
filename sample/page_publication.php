<?php
/**
 *
 * The Template for Publication Static Pages
 *
 * Template Name: Publications
 *
 * @author Takuto Yanagida
 * @version 2021-07-20
 *
 */


get_header();
?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

<?php
while ( have_posts() ) : the_post();
	get_template_part( 'template-parts/entry', 'page' );
endwhile;
?>
			<section>
				<div class="entry-content">
					<?php \wplug\bimeson_post\the_filter(); ?>
					<?php \wplug\bimeson_post\the_list(); ?>
				</div>
			</section>

		</main>
	</div>
<?php
get_footer();
