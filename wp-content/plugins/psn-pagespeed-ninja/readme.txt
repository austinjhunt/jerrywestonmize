=== PageSpeed Ninja - Cache, Minify, Defer CSS JavaScript, Critical CSS, Optimize Images, Convert WebP ===

Stable tag: 1.4.5
Requires at least: 4.6
Tested up to: 6.6.2
Requires PHP: 5.6
Contributors: pagespeed
Tags: performance, convert webp, defer css javascript, minify css, minify javascript, critical css, optimize images, cache, pagespeed, optimization, core web vitals, lazy load
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Boost page speed: cache, compress, optimize images to WebP, minify CSS/JS, defer loading, lazy load, generate critical CSS, improve Core Web Vitals


== Description ==

**PageSpeed Ninja** is the ultimate performance plugin, specially designed to tackle slow loading times on your website on both desktop and mobile platforms. This plugin effortlessly addresses Google PageSpeed Insights issues and significantly improves Core Web Vitals.

This tool offers a range of features designed to optimize your website's performance:

- **Compression:** Implement Gzip and Brotli compression for faster load times.
- **Resolve Render-Blocking Issues:** Address render-blocking CSS and JavaScript issues to streamline loading.
- **Enhancing Critical Rendering Path:** Automatically generate critical CSS for above-the-fold content to improve the critical rendering path.
- **Minification:** Reduce load size by minifying HTML, JavaScript, and CSS files.
- **Resource Bundling:** Combine and inline JavaScript and CSS to reduce server requests.
- **Defer Loading:** Prioritize content rendering by choosing to defer CSS and JavaScript loading.
- **Optimize Images:** Improve loading speed with optimized image formats.
- **Efficient Image Formats:** Convert to WebP format for faster performance.
- **Lazy Load:** Optimize initial load with image lazy loading and optional low-quality placeholders.
- **Optimize Google Fonts:** Streamline Google Fonts loading for quicker rendering.
- **Cache Leveraging:** Utilize browser and server-side caching for improved performance.
- Benefit from 10+ years of experience optimizing 200,000+ mobile-friendly websites to offer even more enhancements, including image optimization, caching, and a suite of front-end and back-end performance improvements.

This plugin is your go-to solution for reducing slow loading times, improving SERP optimization, and boosting website speed, making it an essential tool for performance optimization and SEO.

### Why Choose PageSpeed Ninja?

Are you looking to improve your website's performance and Google search rankings? PageSpeed Ninja excels in core web vitals and SEO optimization. For over a decade, we've been at the forefront of mobile web optimization. You might be familiar with one of our popular projects, [Lazy Load XT](https://github.com/ressio/lazy-load-xt). PageSpeed Ninja represents our expertise gained from optimizing the performance of over 200,000 websites on mobile devices. We believe you won't find a similar, user-friendly, all-in-one solution for boosting the performance your website.

Benefit from our suite of unique features designed to turbocharge your site's loading speed. From innovative critical CSS generation for above-the-fold content to implementation of tagged page caching, we ensure lightning-fast load times that improve critical performance metrics such as Largest Contentful Paint (LCP), Cumulative Layout Shift (CLS), and more.

### Before You Install

Our statistics indicate that the plugin improves the speed of 4 out of 5 websites. However, certain theme and plugin combinations, especially those related to caching and optimization, may lead to compatibility issues. Therefore, our plugin might not suit every website. To preview how PageSpeed Ninja could benefit your site, we've developed a simple tool that allows you to test it before installing it. **We highly recommend** that you visit [PageSpeed.Ninja](http://pagespeed.ninja) and run a test of your website beforehand. Please note: To accurately test your site on PageSpeed.Ninja, it's crucial to temporarily disable any optimizing plugins. This test requires raw data to apply its own optimization.

### Features

#### Presets

People who own, design, or develop websites are constantly looking for efficient ways to boost performance without getting down in tweaking every single setting. This is precisely where the "Presets" feature of the PageSpeed Ninja comes in.

PageSpeed Ninja offers five different presets, each tailored to specific optimization needs:
- **Optimal**: suitable for the majority of websites,
- **Safe**: prioritizes compatibility (only a minimal subset of optimizations is enabled),
- **Compact**: focuses on saving disk space (image optimization and convert to WebP are disabled),
- **Ultra**: aims for maximum optimization (but may be incompatible with some themes and plugins),
- **Experimental**: reserved for testing new, possibly less stable features.

#### PageSpeed Ninja Settings Groups

PageSpeed Ninja organizes its settings into groups aligned with the Google PageSpeed Insight categories. Using the data from the Google PageSpeed Insight speed analysis, the plugin categorizes the settings groups into three distinct classes: Should Fix, Consider Fixing, and Passed.

Within the General settings, users can easily enable or disable each settings group (specific settings depend on the preset selected), and the Advanced settings page offers fine-tuning all settings according to specific preferences.

Each switch in the settings interface is color-coded to reflect its impact on your website's PageSpeed Insights score:
- **Green**: Improves the score.
- **Orange**: Has minimal effect on the score.
- **Red**: Negatively affects the score.

Note that adjusting certain settings may cause related switches to change color due to their interrelated effects on performance.

#### Initial Server Response Time was Short

This feature within the PageSpeed Ninja optimizes server responses by implementing efficient caching mechanisms. By using advanced page cache strategies, it reduces response time to incoming requests (the Time To First Byte metric).

#### Serve Static Assets with an Efficient Cache Policy

This feature of the PageSpeed Ninja optimizes website performance by implementing an effective caching strategy for static resources. This feature refines the cache policy to improve browser caching for elements such as images, CSS, and JavaScript files. It maximizes caching efficiency by instructing the browser on the optimal duration to retain these assets, reducing server requests and accelerating page load times for returning visitors.

#### Enable Text Compression

This setting in the PageSpeed Ninja optimizes website performance by using two powerful compression techniques: Gzip and Brotli compression. Gzip compression, a widely supported method, reduces the size of text-based files such as HTML, CSS, and JavaScript by compressing them before transmission, thereby speeding up website load times. Brotli compression, a newer and more efficient algorithm, further reduces file sizes and improves performance for modern browsers that support this advanced compression method.

#### Preconnect to Required Origins

This feature in the PageSpeed Ninja optimizes website performance by initiating early connections to third-party origins, reducing latency and improving load times. Using DNS prefetching, it proactively resolves domain names for faster connections. By pre-establishing connections to essential domains such as CDNs or external APIs, it reduces handshake time and improves overall page speed.

#### Preload Key Requests

This setting within the PageSpeed Ninja focuses on optimizing website load times by proactively preloading critical resources. This feature strategically identifies and preloads essential assets, such as fonts, scripts, or CSS files that are required for the initial page rendering process. By fetching these key requests ahead of time, it significantly improves page speed.

#### Minify CSS

The "Minify CSS" settings within the PageSpeed Ninja offer a robust set of tools designed to boost website performance by minimizing CSS files. These settings use advanced CSS minification techniques by using a CSS minifier to compress and optimize CSS resources. By reducing CSS size, the plugin significantly improves page load times.

#### Minify JavaScript

The "Minify JavaScript" setting in the PageSpeed Ninja provides powerful tools to optimize website performance by reducing JavaScript file sizes. This feature uses advanced JavaScript minification techniques, employing a JavaScript minifier to compress and condense code, improving load times and overall site speed. By enabling JS compression, Minify JavaScript helps to reduce JavaScript size, optimize script delivery and boost web page efficiency.

#### Eliminate Render-Blocking Resources

This feature of the PageSpeed Ninja significantly improves page loading speed by focusing on critical aspects of optimization. This feature uses various strategies such as Above-the-fold Critical CSS and Non-blocking JavaScripts to streamline the critical path for fast rendering of essential content. The plugin provides options to inline critical CSS and defer (asynchronously lazy load) non-essential CSS for improved performance. In addition, the plugin manages JavaScript by deferring or asynchronously loading scripts to improve the critical rendering path.

#### Ensure Text Remains Visible During Webfont Load

This feature within the PageSpeed Ninja prioritizes the visibility of text while web fonts are loading. It uses the "swap" mode for web fonts to ensure that a fallback font is displayed immediately, preventing a flash of invisible text (FOIT). Additionally, it optimizes the loading of Google Fonts, ensuring that content remains visible during the font-loading process. This optimization significantly improves the user experience and page performance.

#### Avoids Enormous Network Payloads

This setting within the PageSpeed Ninja includes several optimization techniques aimed at reducing excessive data transfer. It includes features such as CSS optimization, minifying HTML, bundling/merging CSS and JavaScript files, async script loading, HTML minification, and optimizing emoji loading. Together, these settings work to reduce file sizes and improve page loading speed by minimizing unnecessary network payloads.

#### Efficiently Encode Images

This setting within the PageSpeed Ninja provide a comprehensive suite of optimize images tools. This feature allows users to fine-tune the optimization process by adjusting JPEG, WebP, and AVIF qualities to ensure efficient compression. With its range of customizable settings, it allows users to optimize and compress images to varying degrees, serving as a powerful picture optimizer.

#### Serve Images in Next-Gen Formats

This feature, a core component of the PageSpeed Ninja, is a key tool for optimizing website images. This feature facilitates the conversion of images into modern formats such as WebP, a next-gen image format known for its superior compression and quality attributes. This setting acts as an image converter, seamlessly converting existing image files into the WebP format, thereby improving website loading speed and performance. By leveraging the capabilities of WebP, this image conversion setting ensures optimal image delivery.

#### Defer Offscreen Images

The "Defer offscreen images" feature within the PageSpeed Ninja offers various optimizations aimed at improving page load times by implementing lazy loading techniques. This feature delays the loading of images, videos, and iframes that are not immediately visible on the user's screen, using image lazyload methods to prioritize content above the fold. The plugin allows users to choose lazy load with one of two types of Low-Quality Image Placeholders (LQIPs): gradient placeholders and blurred low-quality placeholders. These placeholders are displayed in place of the actual images, providing a smoother initial load while the full-quality images lazy load in the background.

#### Image Elements Have Explicit Width and Height

This setting in the PageSpeed Ninja focuses on optimizing the rendering of web pages by ensuring that all images have width and height attributes. This optimization strategy aims to prevent layout shifts during page loading by specifying the exact dimensions for each image element and ensures that browsers pre-allocate space for images based on the provided dimensions, eliminating the need for recalculations when images load.

#### JavaScript Execution Time

This setting in the PageSpeed Ninja provides control over optimizing JavaScript to improve site performance. It enables features like deferring critical JS to prioritize vital scripts for faster loading, optimizing integrations with platforms (like Facebook, Twitter, etc.) to streamline their scripts' loading mechanisms, and delaying the loading of all asynchronous JavaScripts to prevent potential bottlenecks. Additionally, it allows for the selective delay of loading of specific JavaScripts in Advanced settings.

#### Avoids an Excessive DOM Size

This setting within the PageSpeed Ninja focuses on optimizing webpage performance by reducing the Document Object Model (DOM) size. Currently, this is accomplished by eliminating embedded plugins such as Flash, ActiveX, Silverlight. In the Pro version it's possible to hide extra DOM elements and reveal them after user interaction. Ongoing development may introduce further optimization techniques to trim excess DOM elements and improve overall website speed.

#### Avoid serving legacy JavaScript to modern browsers

This setting allow to detect polyfill JavaScript files and load them in legacy browsers only, thereby reducing network payload.

#### Has a Meta Viewport Tag with width or initial-scale

This feature within the PageSpeed Ninja optimizes web pages by ensuring that they contain an important meta viewport tag. This tag is crucial for improving mobile responsiveness. Including this tag allows web content to properly scale and adapt to different devices and screen sizes, ultimately optimizing the page for seamless viewing across a range of devices.

#### Advanced Settings

The Advanced Settings page within the PageSpeed Ninja serves as a central hub for users seeking more control over their website optimization. This section not only allows users to fine-tune settings but also facilitates efficient cache management and provides troubleshooting capabilities. Users can adjust specific parameters to tailor the plugin's performance optimization precisely to their website's requirements.

### Free License Key

Starting from November 2023, PageSpeed Ninja requires a free license key for connectivity to our servers. This important update results in improved server load balancing to prevent resource exhaustion and ensure uninterrupted performance. You can get your free license key by visiting https://pagespeed.ninja/download/.

### PageSpeed Ninja Pro

PageSpeed Ninja Pro is a powerful plugin designed to optimize website performance through a range of advanced features. It drastically reduces load times with its fast advanced page caching and multithreading background optimization, image optimization features that include properly sizing images and AVIF format support. The plugin excels in efficient CLI asset optimization through minification tools such as UglifyJS for JavaScripts, CSSO for stylesheets, JPEGOptim/OptiPNG/GIFsicle for images, and many others. Its DNS Prefetch and Preload Assistants fine-tune site responsiveness. Notably, it allows users to self-host and optimize external resources like Google Analytics and offers robust backup/restore capabilities, culminating in a comprehensive solution for turbocharging website performance.

**[Get PRO with PageSpeed.Ninja](https://pagespeed.ninja/download/)**

### Uninstallation

When you delete the plugin, it will automatically revert all settings to the original state as they were before this plugin was installed. During this process, the "/s" directory containing optimized files will be removed and changes made to ".htaccess" files will be undone. Please note that uninstalling the plugin will remove all data associated with the plugin, including settings and logs.

### Feedback, Bug Reports, and Logging Possible Issues

If you have any questions, suggestions, or encounter issues related to site speed optimization, we encourage you to contact us at hello@pagespeed.ninja. Whether you're a user, developer, or tester, your feedback is essential to improving our services.

To facilitate troubleshooting, PageSpeed Ninja offers error logging capabilities. You can enable this feature in the Advanced tab of the PageSpeed Ninja settings. If you encounter any problems, you can help us in resolving them by providing us with the relevant error log file. Your assistance will help us improve your experience with PageSpeed Ninja.


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/psn-pagespeed-ninja` directory, or install the plugin directly from the WordPress plugins screen. We highly recommend creating a backup of your site beforehand, just as you would before installing any new plugin.
2. Activate the plugin through the "Plugins" screen in the WordPress dashboard.
3. Select the optimization preset in the post-install pop-up window and click Save.
4. Navigate to PageSpeed Ninja and adjust the optimization levels suggested by Google's PageSpeed Insights (**note that all optimizations are disabled by default**). The plugin will then optimize your images, JS and CSS files, and update .htaccess files to fix the issues identified by Google PageSpeed Insights.


== Frequently Asked Questions ==

= Does this plugin have any conflicts with Yoast or any of the other SEO plugins out there? =

The PageSpeed Ninja plugin should work pretty well with most other plugins without issues. However, if some SEO plugins try to do some of the same things as this plugin, then conflicts could be possible especially if gzip compression is enabled. However, that is pretty unlikely.

= Is PageSpeed Ninja compatible with all WordPress themes and plugins?

While PageSpeed Ninja strives to be compatible with a wide range of themes and plugins, there are rare cases where conflicts may occur. It's important to test the plugin in your specific setup and contact the plugin support for assistance.

= Can PageSpeed Ninja guarantee a perfect PageSpeed score?

PageSpeed Ninja offers powerful optimization features, but achieving a perfect PageSpeed score depends on many factors, including your website's structure, hosting environment, and content. The plugin significantly improves performance, but individual scores may vary.

= Can I revert optimizations made by PageSpeed Ninja?

Yes, PageSpeed Ninja allows you to undo optimizations through the plugin's settings. However, it's important to note that undoing optimizations can affect your website's performance and PageSpeed score. As a last resort, you can uninstall the plugin; this process will remove all files associated with the plugin.


== Screenshots ==

1. See improvement suggestions in one place and fix with single click
2. Fine tune to get the best performance using advanced settings


== Changelog ==

= 1.4.5 Stable Release [26 September 2024]
- Fixed prioritization of meta and title tags in "simple" HTML parser

= 1.4.4 Stable Release [10 September 2024]
- Added "Clear Image Errors Cache" button
- Fixed directory size calculation
- Fixed issue with LQIP generation
- Fixed possible warning in YouTube lazy loading [Pro]

= 1.4.3 Stable Release [04 July 2024]
- Fixed issue with daily scheduler
- Fixed issue with Redis cache cleaning [Pro]
- Disabled directory index of cache directory in Apache

= 1.4.2 Stable Release [11 June 2024]
- Added support of JavaScript modules in "Preload key requests"
- Fixed issue with CSS relocation in "Inline CSS for First-Time Visitors"
- Fixed issue with scripts execution order in "Non-blocking JavaScript" mode
- Fixed work of lazy image loading
- Fixed processing of query delimiters in Stream and Pharse parsers
- Improved detection of width and height for SVG images
- Adjusted "Ultra" and "Experimental" optimization presets
- Minor bugfixes

= 1.4.1 Stable Release [07 June 2024]
- Fixed issue with missed files [Free]

= 1.4.0 Stable Release [03 June 2024]
- Added "Display" options for loading Web Fonts/Google Fonts
- Added "Auto Update" option
- Added search field in the Advanced settings
- Added warning about incompatibility with other optimization plugins
- Added new "Does not use passive listeners" section [Pro]
- Added initial support for Redis/KeyDB cache [Pro]
- Fixed issue with disabled Save button
- Fixed issue with JavaScript minification
- Fixed issue with CSS minification
- Fixed check for updates [Pro]
- Improved caching under heavy load
- Improved work of Non-blocking JavaScript feature
- Improved asynchronous loading of CSS with Critical CSS feature
- Improved work of preload tags generation
- Adjusted "Ultra" optimization preset
- Changed initial setup wizard
- Minor bugfixes/typos

[See changelog for all PageSpeed Ninja versions.](https://plugins.svn.wordpress.org/psn-pagespeed-ninja/trunk/changelog.txt)
