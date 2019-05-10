# Domain Mapping

**INACTIVE NOTICE: This plugin is unsupported by WPMUDEV, we've published it here for those technical types who might want to fork and maintain it for their needs.**

## Translations

Translation files can be found at https://github.com/wpmudev/translations

## Domain Mapping is the only plugin of its kind to bundle simple mapping, domain name resale and mapping as a premium service.

As one of the most requested features for Multisite, Domain Mapping has quickly become an essential plugin for every network.

![domain-mapping-735x470](https://premium.wpmudev.org/wp-content/uploads/2009/09/domain-mapping-735x470.jpg) 

Cut the bulk and display beautiful, memorable URLs across an entire Multisite network.


### Simple Setup, Smart Configuration

Already set up your network? Map domains on a subdomain or subdirectory network. Don't worry about confusing settings. Domain Mapping automatically adjusts to fit your network's structure.

### Sell Domain Names Direct

Become a reseller and open new streams of income. Let users search, buy and map as many domain names to their site as they like. Automate distribution and management with eNom and Pro Sites

![enom-integration-735x470](https://premium.wpmudev.org/wp-content/uploads/2009/09/enom-integration-735x470.jpg) 

Sell domain names right inside your WordPress Multisite dashboard.

![image](https://premium.wpmudev.org/wp-content/uploads/2009/09/plugin735x4701.jpg)

Domain Mapping even works with networkwide passwords and global shopping carts.

### Stand Out Performance

Save big on SSL certification with mapping control over individual URLs. Get the only mapping plugin in its class to offer forced HTTPS and to let you use an original URL for things like checkout and payment pages.

## Usage Instructions

### To Get Started:

### Getting Set Up

So first and foremost, download the plugin to your computer and then unzip it. Inside, you will see the following files: 

![domain-mapping-3300-files](https://premium.wpmudev.org/wp-content/uploads/2013/09/domain-mapping-3300-files.png)

Once unpacked, we need to upload all that to your website. Provided you have not changed your plugin directory path, it will look like this: _/wp-content/plugins/_ You will want to upload the inner _domain-mapping_ folder there (that's the one inside the main folder with all the numbers). So log in through FTP or, if you are on a local host, then move that folder over. The path will then be: _/wp-content/plugins/domain-mapping/_ You will see in the _/domain-mapping/_ folder there is a file called _sunrise.php_. We need to move that (move, not copy) to the _/wp-content/_ folder. So the path to that file will be: _/wp-content/sunrise.php_ 

![domain-mapping-3300-sunrise](https://premium.wpmudev.org/wp-content/uploads/2013/09/domain-mapping-3300-sunrise.png)

Thats the files sorted and uploaded. Now we just need crack open your _wp-config.php_ file located within the root of your WordPress installation. On a cPanel server that path might be: _/home/cpanel-username/public-html/wp-config.php_ 

![domain-mapping-3300-wpconfig](https://premium.wpmudev.org/wp-content/uploads/2013/09/domain-mapping-3300-wpconfig.png)

You will have to enter the following line:

**define( 'SUNRISE', 'on' );**

You can pop that right under the WP_DEBUG option, so like this:

**define('WP_DEBUG', false);**

**define( 'SUNRISE', 'on' );**

If for some reason you don't have the WP_DEBUG line, then providing it is just above the following code comment it should work well:

**/* That's all, stop editing! Happy blogging. */**

**Note:** If you add this in the wrong place, you will most likely find issues when adding a domain name to be mapped from your WordPress admin area. Now head on over to Network Admin > Plugins, and _Network Activate_ the plugin. 

![Domain Mapping - Network Activate](https://premium.wpmudev.org/wp-content/uploads/2009/09/Domain-Mapping-4.2.0.3-Network-Activate.png)

We have the plugin installed now, so let's go configure things!

### Configuring Network Settings

The settings are located in the Network Admin area under _Settings > Domain Mapping_. 

![Domain Mapping - Menu](https://premium.wpmudev.org/wp-content/uploads/2009/09/Domain-Mapping-4.2.0.3-Menu.png)

##### Mapping options

There are a few settings that need your attention under the first tab: _Mapping Options_. Let's take a look at them one by one. 

![Enter your multisite IP address.](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-mapping-options-config.jpg)
Enter your multisite IP address.

The _Server IP Address_ is where you enter the IP address of your multisite. This doesn't really affect anything; it appears in your users admin panels, and is only to inform them which IP they must use when mapping their domains. 

![Domain Mapping - Optional Instructions](https://premium.wpmudev.org/wp-content/uploads/2009/09/Domain-Mapping-4.2.0.3-Optional-Instructions.png)

Enter your own instructions if you want.

You can optionally enter your own instructions. When this field is left blank, the plugin's default text will be used.

![Select the administration mapping option.](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-mapping-options-adminmap.jpg)

Select the administration mapping option.

_Administration mapping_ is where you select the domain that will be used in the admin area of sub-sites.

*   _domain entered by the user_ enables you and your users to access the admin area with either the mapped domain (**userdomain.com/wp-admin/**) or the original domain (**usersite.yourdomain.com/wp-admin/**).
*   _mapped domain_ enables you and your users to access the admin area through whichever domain is mapped. Don't worry if you use **usersite.yourdomain.com/wp-admin/**, it will simply forward to **userdomain.com/wp-admin/**.
*   _original domain_ enables you and your users to access the admin area through the original domain only (**usersite.yourdomain.com/wp-admin/**).

![Select the login mapping option.](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-mapping-options-loginmap.jpg)

Select the login mapping option.

_Login mapping_ has similar options as above, but it affects login action.

*   _domain entered by the user_ enables you and your users to login with either the mapped domain (**userdomain.com/wp-admin/**) or the original domain (**usersite.yourdomain.com/wp-admin/**).
*   _mapped domain_ requires you and your users to login with whichever domain is mapped (**userdomain.com/wp-login.php/**).
*   _original domain_ requires you and your users to login through the original domain (**usersite.yourdomain.com/wp-login.php/**).

![Enable/disable cross-domain login.](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-mapping-options-crossdomain.jpg)

Enable/disable cross-domain login.[/caption] Enable _Cross-domain autologin_ if you want the plugin to automatically log you into all sites you have mapped. Will also log your users into all domains they have mapped too!

![Enable/disable domain verification.](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-mapping-options-availability.jpg)

Enable/disable domain verification.

If _Verify domain availability_ is enabled, the plugin will notify your users if their selected domain cannot be mapped. 

![Domain Mapping - Front-end SSL](https://premium.wpmudev.org/wp-content/uploads/2009/09/Domain-Mapping-4.2.0.3-Front-end-SSL.png)

Force https in front-end and/or admin area.[/caption] You can _force https in login and admin pages_, only applicable if you have an SSL certificate. And you can also _force http/https in front-end pages_ as well, applicable if you have SSL enabled. 

![Make domain mapping a Pro Sites feature.](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-mapping-options-prosites.jpg)

Make domain mapping a Pro Sites feature.

_Select Pro Sites Levels_ will only appear if you have the Pro Sites plugin activated on your network. This enables you to make the domain mapping functionality available to certain Pro Site levels only, and charge your end members for the privilege of mapping their own domain via Pro Sites upgrades.

##### Reseller Options

Now let's take a look at the settings under the second tab: _Reseller Options_. 

![Domain Mapping - Reseller options](https://premium.wpmudev.org/wp-content/uploads/2009/09/Domain-Mapping-4.2.0.6-Reseller-options.png)

The _reseller API requests log level_ setting enables you to select how to log requests for domain names that your users purchase from you. The _Reseller provider_ setting allows you to select from the available domain name resellers. You can select from the following providers:

*   _eNom_ - you will need to enter your eNom account information.

![Domain Mapping - Reseller - Enom](https://premium.wpmudev.org/wp-content/uploads/2009/09/Domain-Mapping-4.0.2.6-Reseller-Enom.png)

Your _account id and password_ are the same you use to login to your eNom account. The _Select environment_ setting enables you to specify whether to use a test account at [http://resellertest.enom.com/](http://resellertest.enom.com/) or your live account at [http://www.enom.com/](http://www.enom.com/) Be sure the account ID & password you just entered are for the right account. :) The _SSL Certificate Verification_ setting allows you to enable or disable SSL verification. The _Select payment gateway_ setting will only be visible if you have Pro Sites also active on your network. You can select between the eNom credit card processing services (requires you to have a processing agreement with eNom and a secure SSL connection), or the PayPal payment gateway in Pro Sites.

*   _WHMCS_ - Requires the [WHMCS Provisioning](https://github.com/wpmudev/whmcs-multisite-provisioning) plugin available free on GitHub.

##### Mapped Domains

Let's take a look at the last tab: _Mapped Domains_. This tab provides a great overview of the domains mapped on your network. Domains are shown along with details and options available in columns as follows: Site ID, Mapped Domain, Original Address, Health Status, DNS Configuration, Active, Actions 

![Domain Mapping - Mapped domains](https://premium.wpmudev.org/wp-content/uploads/2009/09/Domain-Mapping-4.2.0.6-Mapped-domains.png)

1.  Easily find domains by entering a search term and clicking the _Search mapped domains_ button.
2.  The Health Status column indicates whether a domain is _valid_ or not. Clicking the link will refresh the field.
3.  The Actions column provides a couple of icons for quick actions: _Toggle scheme_ or _Remove mapping_.

* * *

### Domain Mapping

To add a domain name to your admin area is easy, but keep something in mind here: _If in the previous steps you chose “**Mapped Domain**” as the method for accessing the admin area and the domain being mapped is either not resolving to your server yet or not resolving for another reason other than DNS propagation then that admin area will not be accessible until the domain name has resolved correctly. Choose another option if this bothers you. :-)_ Adding a domain name which is not resolving correctly in this instance will make that admin area inaccessible. Honest, we warned you!! ;-) So, lets go to a sub site which has it own domain to map. In the sub site's dashboard, go to Tools > Domain Mapping. 

![Domain Mapping - Tools Domain Mapping menu](https://premium.wpmudev.org/wp-content/uploads/2009/09/Domain-Mapping-4.2.0.6-Tools-Domain-Mapping-menu.png)

Under the first tab, you can add the domain to be mapped to your sub site. Once you click add then that's all you need to do from WordPress. [caption id="attachment_845501" align="alignnone" width="559"]

![Enter the domain to be mapped to the sub site.](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-settings-site-map.png)

Enter the domain to be mapped to the sub site.[/caption] If you have enabled _Verify domain availability_ in your network settings as described above, and the domain entered here is not valid for some reason, an alert like this will pop up: [caption id="attachment_845497" align="alignnone" width="559"]

![Domain name verification alert.](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-mapping-options-availabilitymessage.png)

Domain name verification alert.[/caption] If you have enabled domain purchasing in your network settings, clicking the second tab (Purchase domain) will enable your users to select and purchase their preferred domain name from you, right in the admin of their site. How cool is that? [caption id="attachment_845503" align="alignnone" width="559"]

![Purchase a domain name right from wp-admin!](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-settings-site-purchase.png)

Purchase a domain name right from the dashboard![/caption]

* * *

### A Record & CNAME

If you or your end user wishes to map a sub domain to the blog within your multisite install then you can set up a CNAME For the purpose of these instructions we will assume you are doing this through cPanel, however if you are using a different panel or managing the DNS with the domain name registrar then the principle is just the same, the method of adding it might be a little different. If you cannot manage your own DNS then you would need to discuss this with whomever is currently managing it. Even people on cPanel might not have access to the Simple and Advanced DNS options, this depends on your hosting provider. If you point your name servers to your host then it can be managed by your host or your cPanel. Again if you don't have access, then ask your host. **CNAMEs** are used for sub domain mapping. i.e. you want to map blog.userdomain.com to usersite.yourdomain.com **A Records** are for mapping TLDs aka Top Level Domains. i.e. userdomain.com mapped into usersite.yourdomain.com First we will handle a CNAME: We will do this through Advanced DNS, so click on that. [caption id="attachment_845505" align="alignnone" width="559"]![Select Advanced DNS in cPanel](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-cpanel-dns.png)

Select Advanced DNS in cPanel[/caption] Select the domain from which you would like to enter a CNAME. Now fill in the form. :-) [caption id="attachment_845507" align="alignnone" width="559"]![Adding a CNAME in cPanel](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-cpanel-cname.png)

Adding a CNAME in cPanel[/caption] _Name:_ the sub domain to be mapped. So subdomain.example.com _TTL:_ You can set this to 14400\. If you know what you are doing then please feel free to adjust this. _Type:_ CNAME of course ;-) _CNAME:_ Where it is going to, in the example above, we use usersite.yourdomain.com Easy Peasy huh! For _A Record - Top Level Domains_ we go through the same process, except you will most likely already have the A record set (unless in instances where the domain is registered but not pointed anywhere). [caption id="attachment_845509" align="alignnone" width="559"]![Adding an A Record in cPanel](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-cpanel-arecord.png)

Adding an A Record in cPanel[/caption] So If its there click on EDIT for the main A record, and change that IP address to the dedicated IP for your Multisite install. If you are setting up A record for the first time then the type is “A” A Record. Then rather than entering a CNAME it will be the Address field, which is for the IP Address. Note: The IP address must have it's DocumentRoot set so that when you load it in your browser you will see your WordPress install. In most cases this should be /public_html/ but it can vary, your host will be able to assist easily. This ensures that any mapped domains will be sent through to your WordPress install.

* * *

### More options?

Yeah there are, you can also use _Addon Domains_ and _Parked Domains_. They must point to the root of your multisite installation in order to function correctly. This method also does not require a dedicated IP address. However its not recommended you use this method for client websites as you will have to manually control their DNS. You would need to deal with MX Records if they wish to use email on that domain as well as other DNS requests. This is a good method if you own them all though, its quick and easy! Remember the domain root needs to be your multisite folder which is usually: _/home/cpanel-username/public-html/_

* * *

### Addon Vs Parked

There really is not much difference. If you want an extra FTP account then use Addon domains. Really you shouldn't be handing FTP accounts out due to security concerns, so there isn't much need for this. And all FTP is usually done through your main FTP account. Parked domains simply does as it states, it parks the domain onto a folder or redirect. Again this will vary from control panel to control panel. [caption id="attachment_845511" align="alignnone" width="559"]![Parking a domain in cPanel](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-cpanel-park.png)

Parking a domain in cPanel[/caption] [caption id="attachment_845512" align="alignnone" width="559"]![Creating an Addon Domain in cPanel](https://premium.wpmudev.org/wp-content/uploads/2013/10/domain-mapping-4000-cpanel-addon.png)

Creating an Addon Domain in cPanel[/caption]

* * *

### Domain List

With Domain Mapping, you'll be able to easily see mapped domains through your sites list at _Sites > All Sites_. ![Domain Mapping - Sites list](https://premium.wpmudev.org/wp-content/uploads/2009/09/Domain-Mapping-4.2.0.3-Sites-list1.png)

Here you'll see an additional column, _Mapped Domains_. ![Domain Mapping - Sites list domains](https://premium.wpmudev.org/wp-content/uploads/2009/09/Domain-Mapping-4.2.0.3-Sites-list-domains.png)

All domain names mapped within your network will be displayed here.

* * *

### Common FAQ

##### I get a 404 or some sort of server page, why?

Chances are your not using a dedicated IP or you have some issue with your htaccess file, please check those out first. You may also wish to double check your permalinks and re-save them again.

##### Why do I need a dedicated IP?

You only need a dedicated IP address when using A Records to map your domain. This is because the domain being mapped needs to land on your WordPress multisite install and if it doesn't then our plugin and WordPress won't know when to map it. On shared hosting platforms, usually the sites IP address will fall on a landing page which is not related to your account in anyway.

##### Doesn't this need to go into the MU folder?

Nope, you no longer need to do that! If however you are running an older version pre-3.0.7 then you must remove those older plugin files before using the new version!

##### When I add a domain, the page just refreshes and nothing happens?

Chances are when you edited your wp-config.php to enter the sunrise information, you put it to far down. Check through these docs again and make the appropriate adjustments.

##### Erm... Cookie syncing does not work?

Cookie syncing is where you log into your mappeddomain.com and subsite.domain.com at the same time. Its this process that allows you to administer and view a mapped domain website whilst being logged in. Its not a process to log you into all mapped domains on a network install.

##### Anything else I need to do?

If you installed your WordPress installation as a subdirectory, then nahh nothing else there. If you installed your WordPress installation as a subdomain version, then we assume here that you've already set up your Wild Card? Don't worry if not, checkout WordPress for a walk through: [http://codex.wordpress.org/Create_A_Network](http://codex.wordpress.org/Create_A_Network "Create A WordPress Network")

##### After adding a domain I can not add a second domain. Is this correct?

If you haven't added anything to your wp-config.php then it's limited to a single domain per site. If you add the following somewhere in it then you will be able to use multiple domains per site.

    define('DOMAINMAPPING_ALLOWMULTI', true);
