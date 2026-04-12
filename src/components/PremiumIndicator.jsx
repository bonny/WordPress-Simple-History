import { __ } from '@wordpress/i18n';

/**
 * Small muted star glyph shown after premium button labels.
 * Communicates "elevated" (not "locked") — aspiration over resentment.
 *
 * Uses a diamond/star character at reduced opacity, visually subordinate
 * to the button text. No badge pill, no lock icon.
 */
export function PremiumIndicator() {
	return (
		<span
			className="sh-PremiumIndicator"
			aria-label={ __( 'Premium feature', 'simple-history' ) }
		>
			&#x2726;
		</span>
	);
}
