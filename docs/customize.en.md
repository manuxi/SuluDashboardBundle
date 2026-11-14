# Customize the dashboard

Everyone can arrange the dashboard for themselves: move tiles, hide the ones they do not need. The page stays quiet until they ask for it.

## Arranging

1. Click **Customize** in the toolbar. Every tile now has a handle on the left (the same grip Sulu uses for blocks) and two small buttons at the top right.
2. **Move:** drag a tile by its handle to another place in its column. The row of figures on top can be reordered inside the row; its tiles do not move into the columns.
3. **Other column:** the arrow button moves a tile to the end of the other column.
4. **Hide:** the eye button hides a tile. In this mode it stays visible, dimmed, so it can be shown again with the same button. Outside of this mode a hidden tile is not shown and does not load its data.
5. Click **Done**. Every change is saved at once, so nothing is lost if the page is left without "Done".

Tiles do not react to clicks while arranging, so no link opens by accident.

## Reset

**Reset** deletes the own arrangement. From then on the automatic balance of the columns decides again (see [Your own tile in 5 minutes](widgets.en.md), "Balance of the columns"), and hidden tiles are shown again.

## What is saved

The arrangement is a user setting of Sulu (`sulu_dashboard.layout`, saved on the server per user, like the filters of the open drafts). It lists the keys of the tiles per place and the hidden ones.

- A saved arrangement wins over the balance of the columns. It is only ever replaced by the user.
- A tile that comes later (a new bundle, a new permission) is not in the list. It is placed automatically, in the shorter column after the others, and the arrangement of the other tiles stays. A tile the user has hidden stays hidden.
- Tiles the user may not see at the moment (permission, `disabled_widgets`) are skipped; their hidden state is kept.
- Tiles cannot be dragged from one column into the other, because the library behind Sulu's block lists (react-sortable-hoc) only sorts within one list. The arrow button does that part.
