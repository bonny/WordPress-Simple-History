import {
	__experimentalDivider as Divider,
	ExternalLink,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getTrackingUrl } from '../functions';

function DashboardFooter() {
	return (
		<>
			<Divider style={ { color: 'var(--sh-color-separator)' } } />

			<HStack
				spacing={ 5 }
				justify="space-around"
				style={ { padding: '1em 0' } }
			>
				<ExternalLink
					title={ __(
						'Visit the Simple History blog for new features, tips and tricks',
						'simple-history'
					) }
					href={ getTrackingUrl(
						'https://simple-history.com/blog/',
						'blog_dashboard_footer'
					) }
				>
					{ __( 'Blog', 'simple-history' ) }
				</ExternalLink>

				<ExternalLink
					title={ __(
						'Get help with common issues or ask questions',
						'simple-history'
					) }
					href={ getTrackingUrl(
						'https://simple-history.com/support/',
						'support_dashboard_footer'
					) }
				>
					{ __( 'Support', 'simple-history' ) }
				</ExternalLink>

				<ExternalLink
					title="View information about premium features"
					href={ getTrackingUrl(
						'https://simple-history.com/premium/',
						'premium_dashboard_footer'
					) }
				>
					{ __( 'Get Premium', 'simple-history' ) }
				</ExternalLink>
			</HStack>
		</>
	);
}

export { DashboardFooter };
