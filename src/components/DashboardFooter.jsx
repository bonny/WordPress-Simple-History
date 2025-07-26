import {
	__experimentalDivider as Divider,
	ExternalLink,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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
					href="https://simple-history.com/blog/?utm_source=wordpress_admin&utm_medium=Simple_History&utm_campaign=blog_link"
				>
					{ __( 'Blog', 'simple-history' ) }
				</ExternalLink>

				<ExternalLink
					title={ __(
						'Get help with common issues or ask questions',
						'simple-history'
					) }
					href="https://simple-history.com/support/?utm_source=wordpress_admin&utm_medium=Simple_History&utm_campaign=support_link"
				>
					{ __( 'Support', 'simple-history' ) }
				</ExternalLink>

				<ExternalLink
					title="View information about premium features"
					href="https://simple-history.com/premium/?utm_source=wordpress_admin&utm_medium=Simple_History&utm_campaign=premium_link"
				>
					{ __( 'Get Premium', 'simple-history' ) }
				</ExternalLink>
			</HStack>
		</>
	);
}

export { DashboardFooter };
