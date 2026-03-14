import { ExternalLink } from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getTrackingUrl } from '../functions';

/**
 * Friendly info notice shown once at the top of the event list
 * when the failed login limit has suppressed attempts.
 *
 * @param {Object} props
 */
export function FailedLoginLimitNotice( props ) {
	const {
		hasFailedLoginLimit,
		failedLoginLimitThreshold,
		failedLoginSuppressedCount,
		hasPremiumAddOn,
		eventsIsLoading,
	} = props;

	if (
		! hasFailedLoginLimit ||
		failedLoginSuppressedCount <= 0 ||
		hasPremiumAddOn ||
		eventsIsLoading
	) {
		return null;
	}

	return (
		<div className="sh-FailedLoginLimitNotice">
			<div className="sh-FailedLoginLimitNotice-icon">
				<span className="dashicons dashicons-info" aria-hidden="true"></span>
			</div>
			<div className="sh-FailedLoginLimitNotice-content">
				<p className="sh-FailedLoginLimitNotice-heading">
					<strong>
						{ __(
							'Failed login throttling active:',
							'simple-history'
						) }
					</strong>{ ' ' }
					{ sprintf(
						/* translators: 1: number of logged attempts, 2: number of suppressed attempts */
						__(
							'Simple History logged the first %1$s failed attempts, then skipped %2$s more to keep your database size down. Successful logins are unaffected.',
							'simple-history'
						),
						failedLoginLimitThreshold.toLocaleString(),
						failedLoginSuppressedCount.toLocaleString()
					) }
				</p>

				<details className="sh-FailedLoginLimitNotice-details">
					<summary>
						{ __(
							'Why were some attempts not logged?',
							'simple-history'
						) }
					</summary>
					<p>
						{ __(
							'When consecutive failed logins exceed the threshold, Simple History logs the first batch and skips the rest. You still get the usernames, IP addresses, and timing you need — without thousands of duplicate rows in your database.',
							'simple-history'
						) }
					</p>
					<p>
						{ createInterpolateElement(
							__(
								'Need to log every attempt or adjust the threshold? Upgrade to <PremiumLink>Simple History Premium</PremiumLink>.',
								'simple-history'
							),
							{
								PremiumLink: (
									<ExternalLink
										href={ getTrackingUrl(
											'https://simple-history.com/add-ons/premium/#limit-number-of-failed-login-attempts',
											'premium_events_loginlimit_notice'
										) }
									/>
								),
							}
						) }
					</p>
				</details>
			</div>
		</div>
	);
}
