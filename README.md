# Integrating Takealot API with WooCommerce/WordPress.

An API integrating WooCommerce and the Takealot API - without going through Marketplace Genie, because that's unnecessary, and probably not even possible anymore.

Forked from Marketplace Genie.  Then attempting to use code to directly integrate WooCommerce with Takealot using a WordPress plugin UI.

Needs to get product data from WooCommerce into Takealot offers:

On product update:
- updating names, descriptions, stock, and prices (based on rules to account for Takealot), etc.
- only list on Takealot if "in stock" on WooCommerce.

On WooCommerce sale:
- update stock value and status.

On Takealot sale: (separate?)
- update stock on WooCommerce.


Marketplace Genie apparently had 2 separate repositories with the same intention.  Need to read code and find out why separate repos.  Possibly one for WordPress, and of for Takealot?
