import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * React hook that checks if the current user has a specific capability.
 *
 * @param {string} capability - The capability to check for (e.g. 'edit_posts', 'publish_posts')
 * @return {boolean} Whether the user has the specified capability
 */
export const useUserHasCapability = ( capability ) => {
	return useSelect(
		( select ) => {
			// Use the current user ID from core-data store
			const currentUserId = select( coreStore ).getCurrentUser()?.id;

			if ( ! currentUserId ) {
				return false;
			}

			const userData = select( coreStore ).getEntityRecord(
				'root',
				'user',
				currentUserId
			);

			if ( ! userData || ! userData.capabilities ) {
				return false;
			}

			return !! userData.capabilities[ capability ];
		},
		[ capability ]
	);
};
