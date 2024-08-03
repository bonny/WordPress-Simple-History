import {
	Button,
	__experimentalHStack as HStack,
	SelectControl,
} from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __, _x, sprintf } from '@wordpress/i18n';
import { chevronLeft, chevronRight } from '@wordpress/icons';

/**
 * To give it the same look as other Gutenberg components it's loosely based on the pagination in the font collection component:
 * - https://github.com/WordPress/gutenberg/pull/63210
 * - https://github.com/WordPress/gutenberg/blob/trunk/packages/edit-site/src/components/global-styles/font-library-modal/font-collection.js#L140
 *
 * @param {Object} props
 */
export function EventsPagination( props ) {
	const { page, totalPages, onClickPrev, onClickNext, onChangePage } = props;

	if ( ! page || ! totalPages ) {
		return null;
	}

	return (
		<div>
			<HStack spacing={ 4 } justify="center">
				<Button
					label={ __( 'Previous page', 'simple-history' ) }
					size="compact"
					onClick={ onClickPrev }
					disabled={ page === 1 }
					accessibleWhenDisabled
					icon={ chevronLeft }
				/>

				<HStack justify="flex-start" expanded={ false } spacing={ 2 }>
					{ createInterpolateElement(
						sprintf(
							// translators: %s: Total number of pages.
							_x(
								'Page <CurrentPageControl /> of %s',
								'paging',
								'simple-history'
							),
							totalPages
						),
						{
							CurrentPageControl: (
								<SelectControl
									aria-label={ __(
										'Current page',
										'simple-history'
									) }
									value={ page }
									options={ [ ...Array( totalPages ) ].map(
										( e, i ) => {
											return {
												label: i + 1,
												value: i + 1,
											};
										}
									) }
									onChange={ onChangePage }
									size="compact"
									__nextHasNoMarginBottom
								/>
							),
						}
					) }
				</HStack>

				<Button
					label={ __( 'Next page', 'simple-history' ) }
					size="compact"
					onClick={ onClickNext }
					disabled={ page === totalPages }
					accessibleWhenDisabled
					icon={ chevronRight }
				/>
			</HStack>
		</div>
	);
}
