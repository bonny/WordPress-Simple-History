import {
	SelectControl,
	DatePicker,
	Flex,
	FlexItem,
	BaseControl,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { getSettings } from "@wordpress/date";
import { useState } from "@wordpress/element";

export function DefaultFilters(props) {
	const {
		dateOptions,
		selectedDateOption,
		setSelectedDateOption,
		searchText,
		setSearchText,
		selectedCustomDateFrom,
		setSelectedCustomDateFrom,
		selectedCustomDateTo,
		setSelectedCustomDateTo,
	} = props;

	const isInvalidDate = (date) => {
		// Future dates are invalid.
		if (date > new Date()) {
			return true;
		}

		return false;
	};

	function CustomDateRange() {
		const firstDayOfWeek = getSettings().l10n.startOfWeek;

		return (
			<Flex justify="start" gap="15">
				<FlexItem style={{ width: "95px" }}>{/* Empty for space */}</FlexItem>
				<FlexItem>
					<BaseControl label={__("From date", "simple-history")}>
						<DatePicker
							startOfWeek={firstDayOfWeek}
							onChange={(nextDate) => setSelectedCustomDateFrom(nextDate)}
							currentDate={selectedCustomDateFrom}
							isInvalidDate={isInvalidDate}
						/>
					</BaseControl>
				</FlexItem>
				<FlexItem>
					<BaseControl label={__("To date", "simple-history")}>
						<DatePicker
							startOfWeek={firstDayOfWeek}
							onChange={(nextDate) => setSelectedCustomDateTo(nextDate)}
							currentDate={selectedCustomDateTo}
							isInvalidDate={isInvalidDate}
						/>
					</BaseControl>
				</FlexItem>
			</Flex>
		);
	}

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

			{selectedDateOption === "customRange" ? <CustomDateRange /> : null}

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
