# pwm Password Manager
also known as: PwN Password Ninja

&copy; Copyright Owen Maule 2015&nbsp;&lt;<a href="mailto:o@owen-m.com">o@owen-m.com</a>&gt;<br />
<a href="http://owen-m.com/" target="_blank">http://owen-m.com/</a><br />
License: GNU Affero General Public License

A web application to manage passwords and associated details, written in PHP.<br />
Initially developed as a competency test for a job application in early May 2015.

Similar products are <a href="http://keepass.info/" rel="nofollow" target="_blank">KeePass</a> and <a href="https://lastpass.com/" rel="nofollow" target="_blank">LastPass</a>.

#How to install

1. Set up a MySQL database and access credentials. Know the database name, and username and password of the database account to access it. You may use something like <a href="http://www.phpmyadmin.net/home_page/docs.php" rel="nofollow" target="_blank">phpMyAdmin<a> to set this up. If you are not sure how to set up a database, perhaps you can ask your server administrator or hosting support. If you wish to minimise set up, the default username and database name are both "pwm". You may configure the tables used, in which case, if you are sharing a database, check that the tables names don't clash.
2. Copy the files to your webserver to an appropriate path which can be accessed from the web. If this is your only site this may be within /var/www - or if you have multiple websites may need to look at your hosting panel to find the DocumentRoot path for the domain you wish to use. You may use this software within a subdirectory of your domain.
3. Modify config.php with your database name, username and password, and tables names. If both tables are in the same database, make sure the database is set in dbname in 'dsn' and you could change 'db_auth_table' to just \`users\` and 'db_pwm_table' to just \`entries\`. (In future I will simplify the default config to be like this.) If the tables are in different databases, you will need to attach the database prefix e.g. \`fred_pwm\`.\`auth\`. The table names in the config file require the \`\` quotes for religious reasons.
<pre><code>
  'dsn' => 'mysql:host=localhost;dbname=pwm;charset=utf8',
  'db_user' => 'pwm',
  'db_password' => 'YOUR_PASSWORD',
  'db_auth_table' => '\`pwm\`.\`users\`',
  'db_pwm_table' => '\`pwm\`.\`entries\`',
</code></pre>
  I suggest you keep the charset=utf8 on the DSN as this can avoid some quirks that SQL Injection exploits take advantage of.
4. Open the web application within your browser, by navigating to the path where you have copied the files.
5. If there are any issues to resolve, you should be guided by the error messages displayed. Please try to solve this yourself by searching online or asking a friend or colleague, however if you are really stuck you may email me at o@owen-m.com and I will try to offer timely support (no promises).
6. If you have HTTPS support, first test that it's working by using the application with https:// in the URL. If that's working, you can enable 'enforce_https' in config.php which will give you much needed over-the-wire security for your users' precious password data. You can also achieve this by uncommenting the lines in .htaccess (features not yet fully tested)
7. Experts only: If you are going to tweak the encryption settings 'salt_length' and/or 'hash_algo', I suggest you do it before registering accounts, otherwise the passwords will need to be reset (feature implementation pending). Make sure to also increase the length of the 'user_password' field in the authentication database schema, to accommodate added salt. It will require 2 extra characters per increment of 'salt_length' (bytes), as the salt is stored in hexidecimal. Likewise the length of 'password' will need to be adjusted in accordance with a change to 'hash_algo', as passed to the PHP <a href="http://php.net/manual/en/function.hash.php" rel="nofollow" target="_blank">hash()</a> function.

#How to use - Authentication

1. Choose a secure but memorable Master Password for your account. Don't forget this password! The idea is that you can forget the other ones once you have inputted them, as you can easily look them up again.
2. Register an account by filling in your email address and password and pressing [Register]. 
3. Later when you come back, login again by filling in the same email address and password that you registered with and pressing [Login].
4. If you have forgotten your password, there will in future be a password [Reset] feature. Sorry this is not yet implemented.

#How to use - Once logged in

1. The menu links near the top are to [Change password] and [Logout], however the change password feature is not yet implemented.
2. The list box shows the labels for your entries. To create your first entry, fill in the form with label, username, password, URL and notes and press [Create].
3. You may select an entry in the list by clicking it and pressing [Select].
4. If you are viewing an entry and change the details, press [Edit] to submit them.
5. When viewing an entry you may press [Delete] to immediately delete the entry. There is no confirmation yet.
6. If an entry is selected i.e. details are displayed, and you instead wish to create a new one, press [New] to prepare the entry form. You may then fill in the fields as a new entry and press [Create] as in step 2.
7. When you have many entries, you will find the search feature useful. Type something from the entries you desire into the search box and press [Search]. This will restrict the entries in the list to the matching ones. It searches against all the fields. To show all of your entries again, removing the restriction, press the [X] button nearby.

#Personal message from the author

I hope you find this software useful. I have certainly enjoyed creating it.<br />
Best wishes, Owen Maule

#Project Roadmap
This is primarily kept in the header of pwm.php - I will try to keep this README in sync with that.

##To do
	Stubbed functionality: change password, reset password
	Encrypt entry data by login password
	Refactor into class hierarchy: appBase <- authentication <- passwordManager
	Missing functionality: password confirmation, password generation,
	    password security analysis, limit failed logins
	Back-end support: FULLTEXT, SQLlite

##Template (default theme) to do
	Front-end Javascript enhancements
	Missing client functionality: show/hide password, copy to clipboard, open website
	Continue searching for the owner of the image and check permission.
	    ( I expect it's okay, it's Tux and GPL software. )

