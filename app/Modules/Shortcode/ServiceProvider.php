<?php
/**
 * @package ContentRestriction
 * @since   1.3.1
 * @version 1.3.1
 */

namespace ContentRestriction\Modules\Shortcode;

use ContentRestriction\Repositories\ModuleRepository;
use ContentRestriction\Repositories\RuleRepository;
use ContentRestriction\Utils\Random;

class ServiceProvider extends \ContentRestriction\Common\ProviderBase {
	public function boot() {
		/**
		 * Add shortcode to restrict specific portion of content.
		 * [content_restriction id="123"] Content Goes Here ... [/content_restriction]
		 */
		add_shortcode( 'content_restriction', [$this, 'shortcode'] );

		/**
		 * Overriding the restrict view modules for shortcode.
		 */
		add_filter( 'content_restriction_replace_before', [$this, 'before_processing_restrict_view'], 10, 2 );
		add_filter( 'content_restriction_blur_before', [$this, 'before_processing_restrict_view'], 10, 2 );
		add_filter( 'content_restriction_randomize_before', [$this, 'before_processing_restrict_view'], 10, 2 );

		/**
		 * Hide Restrict View modules for shortcode in admin dashboard
		 */
		add_filter( 'content_restriction_restrict_view_module_list', [$this, 'hide_restrict_view_modules_in_admin_dashboard'], 10, 2 );
	}

	public function shortcode( $atts, $content = null ) {
		$atts = shortcode_atts(
			[
				'id' => '',
			],
			$atts,
			'content_restriction'
		);

		$rule = ( new RuleRepository() )->get( $atts['id'] );
		if ( ! $rule ) {
			return $content;
		}

		// Skip rule if it's inactive or invalid
		if ( empty( $rule['status'] ) || ! ModuleRepository::is_valid_rule( $rule['rule'] ) ) {
			return $content;
		}

		$who_can_see_key   = ModuleRepository::resolve_rule_module( $rule['rule']['who-can-see'] );
		$what_content_key  = ModuleRepository::resolve_rule_module( $rule['rule']['what-content'] );
		$restrict_view_key = ModuleRepository::resolve_rule_module( $rule['rule']['restrict-view'] );

		if ( 'shortcode' !== $what_content_key ) {
			return $content;
		}

		// Current user has no access, no restrict the content
		$who_can_see_class = ModuleRepository::get_module( $who_can_see_key );

		// Exit if the current user has access to the restricted content
		$who_can_see = new $who_can_see_class( $rule );
		if ( $who_can_see->has_access() ) {
			return;
		}

		if ( 'replace' === $restrict_view_key && isset( $rule['rule']['restrict-view'][$restrict_view_key]['shortcode_content'] ) ) {
			$content = $rule['rule']['restrict-view'][$restrict_view_key]['shortcode_content'];
		} elseif ( 'blur' === $restrict_view_key ) {
			$content = 'blur blurblur blurblur blurblur blurblur blurblur blur';
		} elseif ( 'randomize' === $restrict_view_key ) {
			$content = Random::randomize( $content );
		}

		ob_start();
		echo $content;

		return ob_get_clean();
	}

	public function before_processing_restrict_view( bool $bool, $obj ): bool {

		if ( 'ContentRestriction\Modules\Shortcode\Shortcode' === $obj->what_content ) {
			return false;
		}

		return $bool;
	}

	public function hide_restrict_view_modules_in_admin_dashboard( array $modules, \WP_REST_Request $request ) {

		if ( 'shortcode' === $request->get_param( 'what_content' ) ) {
			$modules = array_filter( $modules, function ( $module ) {
				return in_array( $module['key'], ['blur', 'hide', 'replace', 'randomize'] );
			} );
		}

		return $modules;
	}
}