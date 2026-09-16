/**
 * Icons that @wordpress/icons does not cover.
 *
 * The paths below are Material Symbols (Outlined, weight 400, 24px) from
 * https://github.com/google/material-design-icons, licensed under the Apache
 * License 2.0 — GPL-compatible, so they can ship with the plugin. Only the
 * paths are copied: loading the webfont, or linking Google Fonts from the
 * admin, is not an option for a plugin hosted on wordpress.org.
 *
 * Note the viewBox: Material Symbols draw from the baseline up, so it is
 * "0 -960 960 960", not the "0 0 24 24" @wordpress/icons uses.
 *
 * Prefer an existing @wordpress/icons export when one fits — those follow the
 * admin's own icon styling.
 */

/**
 * Material Symbols "view_agenda": two stacked cards.
 */
export const viewAgenda = (
	<svg viewBox="0 -960 960 960" xmlns="http://www.w3.org/2000/svg">
		<path d="M200-520q-33 0-56.5-23.5T120-600v-160q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v160q0 33-23.5 56.5T760-520H200Zm0-80h560v-160H200v160Zm0 480q-33 0-56.5-23.5T120-200v-160q0-33 23.5-56.5T200-440h560q33 0 56.5 23.5T840-360v160q0 33-23.5 56.5T760-120H200Zm0-80h560v-160H200v160Zm0-560v160-160Zm0 400v160-160Z" />
	</svg>
);

/**
 * Material Symbols "view_headline": four plain lines.
 */
export const viewHeadline = (
	<svg viewBox="0 -960 960 960" xmlns="http://www.w3.org/2000/svg">
		<path d="M160-360v-80h640v80H160Zm0 160v-80h640v80H160Zm0-320v-80h640v80H160Zm0-160v-80h640v80H160Z" />
	</svg>
);
