import { __ } from "@wordpress/i18n";

export const DEFAULT_DATE_OPTIONS = [
  {
    label: __("Custom date range...", "simple-history"),
    value: "customRange",
  },
  {
    label: __("Last day", "simple-history"),
    value: "lastdays:1",
  },
  {
    label: __("Last 7 days", "simple-history"),
    value: "lastdays:7",
  },
  {
    label: __("Last 14 days", "simple-history"),
    value: "lastdays:14",
  },
  {
    label: __("Last 30 days", "simple-history"),
    value: "lastdays:30",
  },
  {
    label: __("Last 60 days", "simple-history"),
    value: "lastdays:60",
  },
];

export const OPTIONS_LOADING = [
  {
    label: __("Loading...", "simple-history"),
    value: "",
  },
];
export const LOGLEVELS_OPTIONS = [
  {
    label: __("Info", "simple-history"),
    value: "info",
  },
  {
    label: __("Warning", "simple-history"),
    value: "warning",
  },
  {
    label: __("Error", "simple-history"),
    value: "error",
  },
  {
    label: __("Critical", "simple-history"),
    value: "critical",
  },
  {
    label: __("Alert", "simple-history"),
    value: "alert",
  },
  {
    label: __("Emergency", "simple-history"),
    value: "emergency",
  },
  {
    label: __("Debug", "simple-history"),
    value: "debug",
  },
];
