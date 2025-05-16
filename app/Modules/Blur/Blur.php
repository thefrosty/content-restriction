<?php
/**
 * @package ContentRestriction
 * @since   1.0.0
 * @version 1.0.0
 */

namespace ContentRestriction\Modules\Blur;

use ContentRestriction\Utils\Random;

class Blur extends \ContentRestriction\Common\RestrictViewBase {

	public function __construct( $who_can_see, $what_content, array $rule ) {
		$this->type         = 'restrict-view';
		$this->module       = 'blur';
		$this->rule         = $rule;
		$this->who_can_see  = $who_can_see;
		$this->what_content = $what_content;
		$this->options      = $rule['rule'][$this->type][$this->module] ?? [];
	}

	/**
	 * Initializes blur protection on restricted content if access is denied.
	 */
	public function boot(): void {
		/**
		 * Allow developers to intervene before applying content blur,
		 * using the 'content_restriction_blur_before' filter. If any
		 * callback returns false, stop further processing.
		 *
		 * @param bool  $continue Whether to proceed with content blur.
		 * @param self  $this     Current instance of the restriction handler.
		 */
		if ( ! apply_filters( 'content_restriction_blur_before', true, $this ) ) {
			return;
		}

		$who_can_see = new $this->who_can_see( $this->rule );
		if ( $who_can_see->has_access() ) {
			return;
		}

		// Hook into all three filters
		add_filter( 'content_restriction_the_title', [$this, 'modify_content'], 10 );
		add_filter( 'content_restriction_the_excerpt', [$this, 'modify_content'], 1 );
		add_filter( 'content_restriction_the_content', [$this, 'modify_content'] );
	}

	/**
	 * Applies blur protection to title, excerpt, or content based on settings.
	 */
	public function modify_content( $content, $type = '' ): string {
		switch ( current_filter() ) {
			case 'content_restriction_the_title':
				$type = 'title';
				break;
			case 'content_restriction_the_excerpt':
				$type = 'excerpt';
				break;
			case 'content_restriction_the_content':
				$type = 'content';
				break;
		}

		if ( ! $type || ! $this->should_apply( $type ) ) {
			return $content;
		}

		$this->post_id = get_the_ID();

		return $this->add_protection( $content );
	}

	public function add_protection( $content ) {
		if ( ! $this->is_allowed() ) {
			return $content;
		}

		$html_tag      = 'div';
		$add_rand_text = apply_filters( 'content_restriction_blur_protection_rand_text', true );
		if ( $add_rand_text ) {
			$content = Random::randomize( $content );
		}

		$blur_level = $this->options['level'] ?? 10;
		$spread     = $this->options['spread'] ?? 10;

		return sprintf(
			'<%s class="aiocr-blur" style="-webkit-filter: blur(%spx); text-shadow: 0 0 %spx #000;">%s</%s>',
			$html_tag,
			esc_attr( $blur_level ),
			esc_attr( $spread ),
			$content,
			$html_tag
		);
	}

	private function is_allowed(): bool {
		$what_content = new $this->what_content( $this->rule );
		$what_content->set_post_id( $this->post_id );
		if ( $what_content->protect() ) {
			return true;
		}

		return false;
	}

	/**
	 * Determines whether blur should be applied to a given content type.
	 */
	private function should_apply( string $type ): bool {
		return in_array( $type, $this->options['apply_to'] ?? [], true );
	}
}