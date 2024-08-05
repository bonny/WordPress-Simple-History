import { EventHeaderItem } from './EventHeaderItem';

export function EventVia( props ) {
	const { event } = props;
	const { via } = event;

	if ( ! via ) {
		return null;
	}

	return <EventHeaderItem>{ via }</EventHeaderItem>;
}
