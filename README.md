# POKE TRACKER
For the moment when that fiendishly complicated spreadsheet was no longer sufficient for my personal monster tracking needs.

# Disclaimer
This is not pretty. As it is entirely for me (and not for you), I wasn't ever necessarily going for beauty, or any kind of universally-accepted measures of goodness. I wrote this whole mess by hand with minimal dependencies and two things in mind: To explore the feasibility of a use case where scraping a bunch of data from specific places on the web and shoving it into a relational database would be cooler and more fun than manual data entry, and to track all my boxed monster collections.

Also, do not judge me for having no tests. I already feel lowkey bad about it, but it's not like I have any users, so my bugs can only disappoint myself.

## Current Capabilities
### Scrapey Chrome Plugin
* Scrapes national and generational (g7-g9) monster dex data from particular websites I visit myself
* Sends that data to the service API with a button push

### Service
* API receives monster data from the plugin and shoves it into the database for use by the website

### Website
* Lists all monsters in the national dex, with various attribute information
* Collection view: Keep track of any number of boxed monster collections, where they're being kept, what box they start in, whether or not I've got whatever should be in that box, and specific information about that particular boxed monster. Most of the good stuff is editable in the collection view.

# TODO
* Images? Maybe images. I almost support images already.
* Set up / edit new collections through the website, as opposed to adding new collections to the database directly
* Fix up some problems with particular monster forms / gender dimorphism / hidden abilities
* Add more features to the UI instead of requiring clairvoyance (or at least memories of last time I edited anything)
