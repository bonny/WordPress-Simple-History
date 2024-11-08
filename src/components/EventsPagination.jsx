import {
	Button,
	__experimentalHStack as HStack,
	SelectControl,
} from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __, _x, sprintf } from '@wordpress/i18n';
import { chevronLeft, chevronRight } from '@wordpress/icons';

const firstPageIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		height="24px"
		viewBox="0 -960 960 960"
		width="24px"
		fill="#5f6368"
	>
		<path d="M250-250v-460h60v460h-60Zm430-3.85L453.85-480 680-706.15 722.15-664l-184 184 184 184L680-253.85Z" />
	</svg>
);

// https://fonts.google.com/icons?selected=Material+Symbols+Outlined:last_page:FILL@0;wght@400;GRAD@0;opsz@24&icon.query=last&icon.size=24&icon.color=%235f6368
const lastPageIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		height="24px"
		viewBox="0 -960 960 960"
		width="24px"
		fill="#5f6368"
	>
		<path d="M280-253.85 237.85-296l184-184-184-184L280-706.15 506.15-480 280-253.85ZM650-250v-460h60v460h-60Z" />
	</svg>
);

/**
 * To give it the same look as other Gutenberg components it's loosely based on the pagination in the font collection component:
 * - https://github.com/WordPress/gutenberg/pull/63210
 * - https://github.com/WordPress/gutenberg/blob/trunk/packages/edit-site/src/components/global-styles/font-library-modal/font-collection.js#L140
 *
 * @param {Object} props
 */
export function EventsPagination( props ) {
	const { page, totalPages, setPage } = props;

	if ( ! page || ! totalPages ) {
		return null;
	}

	const handleFirstPageClick = () => setPage( 1 );
	const handlePrevClick = () => setPage( page - 1 );
	const handleNextClick = () => setPage( page + 1 );
	const handleLastPageClick = () => setPage( totalPages );
	const handleChangePageClick = ( nextValue ) => {
		let pageToGoTo;

		if ( nextValue === 'custom' ) {
			pageToGoTo = prompt( 'Enter page number', 1 );
		} else {
			pageToGoTo = nextValue;
		}

		pageToGoTo = parseInt( pageToGoTo, 10 );

		// Ensure the page is a number and within the bounds of the total pages.
		if ( isNaN( pageToGoTo ) ) {
			pageToGoTo = 1;
		} else if ( pageToGoTo < 1 ) {
			pageToGoTo = 1;
		} else if ( pageToGoTo > totalPages ) {
			pageToGoTo = totalPages;
		}

		setPage( pageToGoTo );
	};

	const selectOptions = [
		{
			label: __( '…', 'simple-history' ),
			value: 'custom',
		},
		...[ ...Array( totalPages ) ].map( ( e, i ) => {
			return {
				label: i + 1,
				value: i + 1,
			};
		} ),
	];

	return (
		<div>
			<HStack spacing={ 4 } justify="center">
				<Button
					label={ __( 'First page', 'simple-history' ) }
					size="compact"
					onClick={ handleFirstPageClick }
					disabled={ page === 1 }
					accessibleWhenDisabled
					icon={ firstPageIcon }
				/>

				<Button
					label={ __( 'Previous page', 'simple-history' ) }
					size="compact"
					onClick={ handlePrevClick }
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
									options={ selectOptions }
									onChange={ handleChangePageClick }
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
					onClick={ handleNextClick }
					disabled={ page === totalPages }
					accessibleWhenDisabled
					icon={ chevronRight }
				/>

				<Button
					label={ __( 'Last page', 'simple-history' ) }
					size="compact"
					onClick={ handleLastPageClick }
					disabled={ page === totalPages }
					accessibleWhenDisabled
					icon={ lastPageIcon }
				/>
			</HStack>
		</div>
	);
}
