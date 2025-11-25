/**
 * Dynamic Table of Contents Block
 *
 * Gutenberg block for inserting the Dynamic TOC into posts via the block editor.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	...metadata,
	edit: EditComponent,
	save: SaveComponent,
} );

/**
 * Edit component for the block editor.
 */
function EditComponent() {
	const blockProps = useBlockProps( {
		className: 'ttm-toc ttm-toc--editor-preview',
	} );

	return (
		<div { ...blockProps }>
			<nav className="ttm-toc">
				<h2 className="screen-reader-text">
					{ __( 'Table of contents', 'ttm-dynamic-toc' ) }
				</h2>

				<div className="ttm-toc__toggle">
					<button
						className="ttm-toc__button"
						type="button"
						aria-expanded="false"
						disabled
					>
						<span className="ttm-toc__title">
							{ __( 'Table of contents', 'ttm-dynamic-toc' ) }
						</span>
						<span className="ttm-toc__info">
							<span className="ttm-toc__count">(preview)</span>
							<span
								className="ttm-toc__icon"
								aria-hidden="true"
							>
								+
							</span>
						</span>
					</button>
				</div>

				<div
					className="ttm-toc__panel"
					role="region"
					hidden
					aria-label={ __(
						'Table of contents',
						'ttm-dynamic-toc'
					) }
				>
					<p style={ { padding: '1em', color: '#666' } }>
						{ __(
							'The table of contents will be generated from the headings in your post when published.',
							'ttm-dynamic-toc'
						) }
					</p>
				</div>
			</nav>
		</div>
	);
}

/**
 * Save component — renders the [dynamic_toc] shortcode.
 * The shortcode is processed on the frontend to generate the actual TOC.
 */
function SaveComponent() {
	return <div>[dynamic_toc]</div>;
}
