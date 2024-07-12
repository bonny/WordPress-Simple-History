import apiFetch from "@wordpress/api-fetch";
import {
	BaseControl,
	Flex,
	FlexBlock,
	FlexItem,
	FormTokenField,
} from "@wordpress/components";
import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { addQueryArgs } from "@wordpress/url";
import { LOGLEVELS_OPTIONS } from "./constants";

/**
 * More filters that are hidden by default.
 * Includes log levels, message types and users.
 *
 * @param {object} props
 */
export function ExpandedFilters(props) {
	const {
		messageTypesSuggestions,
		selectedLogLevels,
		setSelectedLogLevels,
		selectedMessageTypes,
		setSelectedMessageTypes,
		selectedUsers,
		setSelectUsers,
	} = props;

	const [userSuggestions, setUserSuggestions] = useState([]);

	// Generate loglevels suggestions based on LOGLEVELS_OPTIONS.
	// This way we can find the original untranslated label.
	const LOGLEVELS_SUGGESTIONS = LOGLEVELS_OPTIONS.map((logLevel) => {
		return logLevel.label;
	});

	const searchUsers = async (searchText) => {
		if (searchText.length < 2) {
			return;
		}

		apiFetch({
			path: addQueryArgs("/simple-history/v1/search-user", {
				q: searchText,
			}),
		}).then((searchUsersResponse) => {
			let userSuggestions = [];
			searchUsersResponse.map((user) => {
				userSuggestions.push(`${user.display_name} (${user.user_email})`);
			});
			setUserSuggestions(userSuggestions);
		});
	};

	return (
		<div>
			<Flex align="top" gap="0">
				<FlexItem style={{ margin: "1em 0" }}>
					<label className="SimpleHistory__filters__filterLabel">
						{__("Log levels", "simple-history")}
					</label>
				</FlexItem>
				<FlexBlock>
					<div
						class="SimpleHistory__filters__loglevels__select"
						style={{
							width: "310px",
							backgroundColor: "white",
						}}
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
							label=""
							placeholder={__("All log levels", "simple-history")}
							onChange={(nextValue) => {
								setSelectedLogLevels(nextValue);
							}}
							suggestions={LOGLEVELS_SUGGESTIONS}
							value={selectedLogLevels}
						/>
					</div>
				</FlexBlock>
			</Flex>

			<Flex align="top" gap="0">
				<FlexItem style={{ margin: "1em 0" }}>
					<label className="SimpleHistory__filters__filterLabel">
						{__("Message types", "simple-history")}
					</label>
				</FlexItem>
				<FlexBlock>
					<div
						class="SimpleHistory__filters__loglevels__select"
						style={{
							width: "310px",
							backgroundColor: "white",
						}}
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
							label=""
							placeholder={__("All message types", "simple-history")}
							onChange={(nextValue) => {
								setSelectedMessageTypes(nextValue);
							}}
							suggestions={messageTypesSuggestions.map((suggestion) => {
								return suggestion.label;
							})}
							value={selectedMessageTypes}
						/>
					</div>
				</FlexBlock>
			</Flex>

			<Flex align="top" gap="0">
				<FlexItem style={{ margin: "1em 0" }}>
					<label className="SimpleHistory__filters__filterLabel">
						{__("Users", "simple-history")}
					</label>
				</FlexItem>
				<FlexBlock>
					<div
						class="SimpleHistory__filters__loglevels__select"
						style={{
							width: "310px",
							backgroundColor: "white",
						}}
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
							label=""
							placeholder={__("All users", "simple-history")}
							onChange={(nextValue) => {
								setSelectUsers(nextValue);
							}}
							onInputChange={(value) => {
								searchUsers(value);
							}}
							suggestions={userSuggestions}
							value={selectedUsers}
						/>
					</div>
					<BaseControl
						__nextHasNoMarginBottom
						help="Enter 2 or more characters to search for users."
					/>
				</FlexBlock>
			</Flex>
		</div>
	);
}
