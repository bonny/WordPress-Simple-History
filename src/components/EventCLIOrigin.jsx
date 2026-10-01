import { __, sprintf } from '@wordpress/i18n';
import { EventHeaderItem } from './EventHeaderItem';

/**
 * Describe the server user, for example "as par" or "as par via sudo as www-data".
 *
 * @param {Object} cliOrigin
 * @return {string} Text, or an empty string when no user is known.
 */
function getUserText( cliOrigin ) {
	const { process_user: processUser, sudo_user: sudoUser } = cliOrigin;

	if ( sudoUser && processUser && sudoUser !== processUser ) {
		return sprintf(
			/* translators: 1: server user who ran sudo, 2: server user the command ran as. */
			__( 'as %1$s via sudo as %2$s', 'simple-history' ),
			sudoUser,
			processUser
		);
	}

	const user = sudoUser || processUser;

	if ( ! user ) {
		return '';
	}

	/* translators: %s: server username. */
	return sprintf( __( 'as %s', 'simple-history' ), user );
}

/**
 * Shows where a WP-CLI command came from, for example
 * "wp plugin deactivate over SSH from 203.0.113.x as par".
 *
 * The SSH address and sudo user come from environment variables that anyone
 * with shell access can set, so the text reports them and claims nothing more.
 *
 * @param {Object} props
 * @param {Object} props.event
 */
export function EventCLIOrigin( props ) {
	const { event } = props;
	const cliOrigin = event?.cli_origin;

	if ( ! cliOrigin ) {
		return null;
	}

	const sshText = cliOrigin.ssh_client_ip
		? sprintf(
				/* translators: %s: IP address the SSH connection came from. */
				__( 'over SSH from %s', 'simple-history' ),
				cliOrigin.ssh_client_ip
		  )
		: '';

	const details = [ sshText, getUserText( cliOrigin ) ]
		.filter( Boolean )
		.join( ' ' );

	return (
		<EventHeaderItem className="SimpleHistoryLogitem__cliOrigin">
			{ cliOrigin.command && <code>wp { cliOrigin.command }</code> }
			{ cliOrigin.command && details && ' ' }
			{ details }
		</EventHeaderItem>
	);
}
