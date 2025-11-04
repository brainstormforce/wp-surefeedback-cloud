<?php

namespace SureFeedback\Http\Controllers\Api;

defined( 'ABSPATH' ) || exit;

use SureFeedback\Http\Controllers\Controller;
use SureFeedback\Repositories\PageSettingsRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Page Settings Controller
 *
 * Handles page-specific widget visibility settings.
 *
 * @package SureFeedback\App\Http\Controllers\Api
 * @author Anurag Singh <anurags@bsf.io>
 */
class PageSettingsController extends Controller {

	/**
	 * Page Settings Repository instance
	 *
	 * @var PageSettingsRepository
	 */
	private $repository;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->repository = new PageSettingsRepository();
	}

	/**
	 * Get all pages with widget status
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		try {
			$pages    = $this->repository->getAllPagesWithStatus();
			$settings = $this->repository->getPageSettings();

			return $this->success(
				array(
					'pages'    => $pages,
					'settings' => $settings,
					'total'    => count( $pages ),
				)
			);
		} catch ( \Exception $e ) {
			return $this->error(
				__( 'Failed to fetch pages', 'surefeedback' ),
				500,
				array( 'error' => $e->getMessage() )
			);
		}
	}

	/**
	 * Update page settings
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		try {
			$settings = $request->get_param( 'settings' );

			if ( ! is_array( $settings ) ) {
				return $this->error( __( 'Invalid settings format', 'surefeedback' ), 400 );
			}

			$updated = $this->repository->updatePageSettings( $settings );

			if ( $updated ) {
				return $this->success(
					array(
						'message'  => __( 'Page settings updated successfully', 'surefeedback' ),
						'settings' => $this->repository->getPageSettings(),
					)
				);
			}

			return $this->error( __( 'Failed to update page settings', 'surefeedback' ), 500 );
		} catch ( \Exception $e ) {
			return $this->error(
				__( 'Failed to update page settings', 'surefeedback' ),
				500,
				array( 'error' => $e->getMessage() )
			);
		}
	}

	/**
	 * Enable widget for specific page
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function enablePage( WP_REST_Request $request ): WP_REST_Response {
		try {
			$page_id = $request->get_param( 'page_id' );

			if ( ! $page_id ) {
				return $this->error( __( 'Page ID is required', 'surefeedback' ), 400 );
			}

			$updated = $this->repository->enableWidgetForPage( $page_id );

			if ( $updated ) {
				return $this->success(
					array(
						'message' => __( 'Widget enabled for page', 'surefeedback' ),
						'page_id' => $page_id,
					)
				);
			}

			return $this->error( __( 'Failed to enable widget', 'surefeedback' ), 500 );
		} catch ( \Exception $e ) {
			return $this->error(
				__( 'Failed to enable widget', 'surefeedback' ),
				500,
				array( 'error' => $e->getMessage() )
			);
		}
	}

	/**
	 * Disable widget for specific page
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function disablePage( WP_REST_Request $request ): WP_REST_Response {
		try {
			$page_id = $request->get_param( 'page_id' );

			if ( ! $page_id ) {
				return $this->error( __( 'Page ID is required', 'surefeedback' ), 400 );
			}

			$updated = $this->repository->disableWidgetForPage( $page_id );

			if ( $updated ) {
				return $this->success(
					array(
						'message' => __( 'Widget disabled for page', 'surefeedback' ),
						'page_id' => $page_id,
					)
				);
			}

			return $this->error( __( 'Failed to disable widget', 'surefeedback' ), 500 );
		} catch ( \Exception $e ) {
			return $this->error(
				__( 'Failed to disable widget', 'surefeedback' ),
				500,
				array( 'error' => $e->getMessage() )
			);
		}
	}

	/**
	 * Enable widget for all pages
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function enableAll( WP_REST_Request $request ): WP_REST_Response {
		try {
			$updated = $this->repository->enableWidgetForAllPages();

			if ( $updated ) {
				return $this->success(
					array(
						'message' => __( 'Widget enabled for all pages', 'surefeedback' ),
					)
				);
			}

			return $this->error( __( 'Failed to enable widget for all pages', 'surefeedback' ), 500 );
		} catch ( \Exception $e ) {
			return $this->error(
				__( 'Failed to enable widget for all pages', 'surefeedback' ),
				500,
				array( 'error' => $e->getMessage() )
			);
		}
	}

	/**
	 * Disable widget for all pages
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function disableAll( WP_REST_Request $request ): WP_REST_Response {
		try {
			$updated = $this->repository->disableWidgetForAllPages();

			if ( $updated ) {
				return $this->success(
					array(
						'message' => __( 'Widget disabled for all pages', 'surefeedback' ),
					)
				);
			}

			return $this->error( __( 'Failed to disable widget for all pages', 'surefeedback' ), 500 );
		} catch ( \Exception $e ) {
			return $this->error(
				__( 'Failed to disable widget for all pages', 'surefeedback' ),
				500,
				array( 'error' => $e->getMessage() )
			);
		}
	}
}
