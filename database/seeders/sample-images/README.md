# Sample item images

Hand-picked photos used by [DatabaseSeeder](../DatabaseSeeder.php) so that a freshly
seeded local database has visually meaningful item thumbnails.

All photos are from **Unsplash** and licensed under the
[Unsplash License](https://unsplash.com/license) (free to use, no attribution required;
credits below as a courtesy).

| File | Unsplash photo ID | URL |
|---|---|---|
| `garage.jpg` | `AHnhdjyTNGM` | https://unsplash.com/photos/AHnhdjyTNGM |
| `toolbox.jpg` | `Rf9eElW3Qxo` | https://unsplash.com/photos/Rf9eElW3Qxo |
| `lawnmower.jpg` | `8vBpYpTGo90` | https://unsplash.com/photos/8vBpYpTGo90 |
| `bicycle.jpg` | `tG36rvCeqng` | https://unsplash.com/photos/tG36rvCeqng |
| `kitchen.jpg` | `4jxGry4pXtc` | https://unsplash.com/photos/4jxGry4pXtc |
| `coffee-maker.jpg` | `RZJRWMnd0DM` | https://unsplash.com/photos/RZJRWMnd0DM |
| `blender.jpg` | `dH67nSuFkv8` | https://unsplash.com/photos/dH67nSuFkv8 |
| `office.jpg` | `wJ7yGwz2-00` | https://unsplash.com/photos/wJ7yGwz2-00 |
| `laptop.jpg` | `1SAnrIxw5OY` | https://unsplash.com/photos/1SAnrIxw5OY |

These photos are intentionally committed to the repo so seeding works offline and
the demo experience is reproducible. They're only used by `DatabaseSeeder`; production
items get their photos from user uploads via the `ItemImageManager` UI.
