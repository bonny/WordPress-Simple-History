# Issue #583: Generate history based on existing WP data

**Status**: Experimental
**Labels**: Experimental feature, Feature
**Project Board**: Simple History kanban (Experimental)

## Overview

When the plugin is installed it contains no history at all. This is a sad and boring empty state. Can we after install pull in any data from the WordPress installation and populate the log?

The information available in WordPress for historical events are very limited, but perhaps we can pull in post and page changes.

## Requirements

- Issue #584 needs to be implemented first (or events imported after a while will come up way wrong in the log)

## Todo

- [ ] Add tools page with import information for core users and import functionality for premium users
- [ ] Pre-fill log by importing 60 days back when plugin is installed

## Progress

*Document findings, progress, and notes here as work progresses*
