<?php
/**
 * @package ContentRestriction
 * @since   1.0.0
 * @version 1.0.0
 */

namespace ContentRestriction\Modules\Replace;

use ContentRestriction\Utils\Analytics;

class Replace extends \ContentRestriction\Common\RestrictViewBase {

	public function __construct( $who_can_see, $what_content, array $rule ) {
		$this->type         = 'restrict-view';
		$this->module       = 'replace';
		$this->rule         = $rule;
		$this->who_can_see  = $who_can_see;
		$this->what_content = $what_content;
		$this->options      = $rule['rule'][$this->type][$this->module] ?? [];
	}

	/**
	 * Initializes content restriction checks and applies modifications as needed.
	 */
	public function boot(): void {

		/**
		 * Allow developers to intervene before applying content replacement,
		 * using the 'content_restriction_replace_before' filter. If any
		 * callback returns false, stop further processing.
		 *
		 * @param bool  $continue Whether to proceed with content replacement.
		 * @param self  $this     Current instance of the restriction handler.
		 */
		if ( ! apply_filters( 'content_restriction_replace_before', true, $this ) ) {
			return;
		}

		// Exit early if the current user has access to the restricted content
		$who_can_see = new $this->who_can_see( $this->rule );
		if ( $who_can_see->has_access() ) {
			return;
		}

		// Log that the user encountered restricted content
		Analytics::add( [
			'user_id' => get_current_user_id(),
			'context' => 'locked',
			'id'      => $this->rule['id'],
		] );

		// Attach filters to modify restricted content areas as specified by the rule
		add_filter( 'content_restriction_the_title', [$this, 'modify_content'], 10 );
		add_filter( 'content_restriction_the_excerpt', [$this, 'modify_content'], 1 );
		add_filter( 'content_restriction_the_content', [$this, 'modify_content'], 10 );
	}

	public function modify_content( $content ): string {
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

		$this->post_id = get_the_ID() ?: 0;
		$override      = (string) $this->options[$type] ?? '';

		return ! empty( $override ) ? $this->add_protection( $content, $override ) : $content;
	}

	private function add_protection( string $content, string $override ) {
		if ( ! $this->is_allowed() ) {
			return $content;
		}

		return $override;
	}

	private function is_allowed(): bool {
		$what_content = new $this->what_content( $this->rule );
		$what_content->set_post_id( $this->post_id );
		if ( $what_content->protect() ) {
			return true;
		}

		return false;
	}
}