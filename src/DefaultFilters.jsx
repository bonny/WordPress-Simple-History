import { SelectControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

export function DefaultFilters(props) {
	const {
		dateOptions,
		selectedDateOption,
		setSelectedDateOption,
		searchText,
		setSearchText,
	} = props;

	return (
		<>
			<p>
				<label className="SimpleHistory__filters__filterLabel">
					{__("Dates", "simple-history")}
				</label>
				<div style={{ display: "inline-block", width: "310px" }}>
					<SelectControl
						options={dateOptions}
						value={selectedDateOption}
						onChange={(value) => setSelectedDateOption(value)}
					/>
				</div>
			</p>
			<p>
				<label className="SimpleHistory__filters__filterLabel">
					Containing words:
				</label>
				<input
					type="search"
					className="SimpleHistoryFilterDropin-searchInput"
					value={searchText}
					onChange={(event) => setSearchText(event.target.value)}
				/>
			</p>
		</>
	);
}
