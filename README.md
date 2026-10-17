<h1>Design Cart Migration 🚀</h1>
<p><strong>Migrate WordPress and WooCommerce databases without spending hours manually fixing broken URLs.</strong></p>
<p>Design Cart Migration is a free, open-source WordPress plugin designed to solve one of the most frustrating problems developers face when moving websites between environments: <strong>old URLs hidden inside complex database structures.</strong></p>
<p>Traditional search-and-replace tools work well in many situations, but real-world WordPress and WooCommerce migrations can involve much more than replacing plain text.</p>
<p>URLs may be stored inside JSON objects, PHP-serialized arrays, nested structures, associative array keys, or strings containing escaped slashes.</p>
<p>And sometimes, even after a successful migration, references to <code>localhost</code> remain scattered throughout the database.</p>
<p>That's exactly why I created Design Cart Migration.</p>
<p>🔗 <a href="https://www.designcart.pl/laboratorium/379-design-cart-migration-migracja-wordpressa-i-woocommerce-bez-recznego-poprawiania-setek-adresow-url.html"><strong>Official Plugin Page – Design Cart Migration</strong></a></p>
<p>👨‍💻 <a href="https://www.designcart.pl/pawel-nosko.html"><strong>About the Author – Paweł Nosko, Design Cart</strong></a></p>
<div><hr></div>
<h2>💡 Why I Built This Plugin</h2>
<p>For years, I used Better Search Replace when migrating WordPress websites.</p>
<p>It's a useful tool, but during some complex WooCommerce migrations, I repeatedly encountered URLs that required additional manual corrections.</p>
<p>For example:</p>
<pre><code>http://localhost/shop
http:\/\/localhost\/shop
https://localhost/shop
\/\/localhost\/shop</code></pre>
<p>Sometimes the old URL appeared as an associative array key rather than a value. Other times, it was hidden inside JSON embedded in PHP-serialized data.</p>
<p>Fixing these cases manually was repetitive, time-consuming, and unnecessary.</p>
<p>I wanted a tool that understood how WordPress plugins actually store their data.</p>
<p>So I built one.</p>
<h2>✨ Key Features</h2>
<h3>🧠 Smart URL Replacement</h3>
<p>Automatically handles common URL variations, including:</p>
<ul>
<li>
<p>HTTP and HTTPS protocols</p>
</li>
<li>
<p>Trailing slashes</p>
</li>
<li>
<p>Protocol-relative URLs</p>
</li>
<li>
<p>JSON-escaped slashes</p>
</li>
<li>
<p>Common escaped URL representations</p>
</li>
<li>
<p>Longest-match-first replacement</p>
</li>
</ul>
<h3>🔄 Recursive Data Processing</h3>
<p>Processes supported nested data structures instead of treating everything as plain text.</p>
<ul>
<li>
<p>PHP-serialized arrays and structures</p>
</li>
<li>
<p>JSON objects and arrays</p>
</li>
<li>
<p>Nested JSON and serialized data</p>
</li>
<li>
<p>Associative array keys and values</p>
</li>
<li>
<p>Regular text strings</p>
</li>
</ul>
<h3>📦 SQL Export with URL Replacement</h3>
<p>Export your WordPress database with URLs already converted to the destination domain.</p>
<p><strong>No need to import the old database and then run a separate search-and-replace operation on the destination server.</strong></p>
<h3>⚡ Live Database Replacement</h3>
<p>Replace URLs directly in your current WordPress database.</p>
<p>Includes a dry-run mode to preview changes without modifying stored data.</p>
<h3>📊 Table Selection and Progress Tracking</h3>
<ul>
<li>
<p>Select individual database tables</p>
</li>
<li>
<p>View approximate table sizes</p>
</li>
<li>
<p>Select or deselect all tables</p>
</li>
<li>
<p>Exclude large tables (10 MB or more)</p>
</li>
<li>
<p>Monitor SQL export progress</p>
</li>
<li>
<p>Review per-table replacement results in Live Replace</p>
</li>
</ul>
<h3>🛡️ Additional Controls</h3>
<ul>
<li>
<p>WordPress administrator permission checks</p>
</li>
<li>
<p>Nonce verification</p>
</li>
<li>
<p>Optional GUID replacement</p>
</li>
<li>
<p>Confirmation before live database changes</p>
</li>
<li>
<p>Batch-based database processing</p>
</li>
</ul>
<blockquote>
<p>Always create a database backup before performing live replacements.</p>
</blockquote>
<h2>🛠️ Two Ways to Migrate</h2>
<table>
<tbody>
<tr>
<th>Feature</th>
<th>SQL Export</th>
<th>Live Replace</th>
</tr>
<tr>
<td>Smart URL replacement</td>
<td>✅</td>
<td>✅</td>
</tr>
<tr>
<td>JSON processing</td>
<td>✅</td>
<td>✅</td>
</tr>
<tr>
<td>PHP serialization handling</td>
<td>✅</td>
<td>✅</td>
</tr>
<tr>
<td>Associative array key replacement</td>
<td>✅</td>
<td>✅</td>
</tr>
<tr>
<td>Table selection</td>
<td>✅</td>
<td>✅</td>
</tr>
<tr>
<td>Optional GUID replacement</td>
<td>✅</td>
<td>✅</td>
</tr>
<tr>
<td>Dry-run preview</td>
<td>—</td>
<td>✅</td>
</tr>
<tr>
<td>Downloadable SQL file</td>
<td>✅</td>
<td>—</td>
</tr>
</tbody>
</table>
<h2>🚀 Installation</h2>
<ol start="1">
<li>
<p>Download the plugin ZIP archive.</p>
</li>
<li>
<p>Open your WordPress administration panel.</p>
</li>
<li>
<p>Navigate to <strong>Plugins → Add New → Upload Plugin</strong>.</p>
</li>
<li>
<p>Install and activate Design Cart Migration.</p>
</li>
<li>
<p>Open <strong>Migration</strong> in the WordPress admin menu.</p>
</li>
<li>
<p>Choose <strong>SQL Export</strong> or <strong>Live Replace</strong>.</p>
</li>
</ol>
<h2>📋 Requirements</h2>
<ul>
<li>
<p>WordPress 6.2 or newer</p>
</li>
<li>
<p>PHP 7.4 or newer</p>
</li>
<li>
<p>Administrator privileges</p>
</li>
</ul>
<h2>🎯 Who Is It For?</h2>
<p>Design Cart Migration is particularly useful for WordPress developers, WooCommerce store builders, freelancers, web agencies, and administrators who regularly move websites from localhost to staging or production environments.</p>
<p>The plugin focuses on <strong>database migration and URL replacement</strong>. It does not automatically transfer WordPress files, media, themes, or plugins.</p>
<h2>❤️ Built by Design Cart</h2>
<p>Design Cart Migration was developed by <strong>Paweł Nosko</strong>, founder of Design Cart, as a practical solution to recurring problems encountered during WordPress and WooCommerce migrations.</p>
<p>The goal is simple: spend less time fixing old URLs and more time building better websites.</p>
<p>🌐 <a href="https://www.designcart.pl/laboratorium/379-design-cart-migration-migracja-wordpressa-i-woocommerce.html">Read the full project documentation on Design Cart</a></p>
<p>👨‍💻 <a href="https://www.designcart.pl/pawel-nosko.html">Meet the author – Paweł Nosko</a></p>
<h2>📄 License</h2>
<p>GPLv2 or later.</p>
<p>Free and open-source software.</p>
