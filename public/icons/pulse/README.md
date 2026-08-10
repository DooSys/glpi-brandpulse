# Pulse Icons

This directory contains the local SVG icon pack used by GLPI BrandPulse Pulse counters.

The current pack is a Solar Icon Set outline subset copied into category folders. The generated manifest.json indexes every SVG recursively and exposes:

- path: stable path relative to public/icons/pulse/
- label: display label
- category: folder/category name
- keywords: search terms used by the configuration modal

Configuration values use the stable syntax:

`	ext
pulse:Category/Icon.svg
`

Example:

`	ext
pulse:Notifications/Bell.svg
pulse:List/Checklist Minimalistic.svg
pulse:Search/Magnifer.svg
`

BrandPulse also accepts a custom SVG URL or path from the Pulse configuration screen.
