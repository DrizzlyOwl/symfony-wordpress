<?php

namespace Scaffold;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use GuzzleHttp\ClientInterface;

class Commander extends Command{

	private $client;
	private $directory;
	private $wp_folder;

	public function __construct(ClientInterface $client){
		$this->client = $client;

		parent::__construct();
	}

	public function configure(){
		$this->setName("setup")
		->setDescription("Set up a new WordPress install");
	}

	public function execute(InputInterface $input, OutputInterface $output){
		$helper = $this->getHelper("question");
		$fs = new Filesystem();

		// make sure that the folder doesn't already exist
		$question = new Question("Please enter the name of the directory you wish to install WordPress to: ", "wordpress");
		$this->directory = $helper->ask($input, $output, $question);

		// ask if the user wants to continue
		$question = new ConfirmationQuestion("Are you sure you want to install to '" . $this->directory . "' Y/N? ", false);
		if(!$helper->ask($input, $output, $question)){
			$output->writeln("<error>Exiting</error>");
			return;
		}

		// download and set up WP CLI globally if it doesn't already exist
		$bin = "/usr/local/bin";
		if($fs->exists($bin . "/wp")){
			$output->writeln("<comment>wp-cli.phar found at " . $bin . "/wp</comment>");
			$out = shell_exec("wp cli update --yes");
			$output->writeln($out);

		}else{
			$output->writeln("<comment>Download WordPress command-line interface. Please wait...</comment>");
			$this->download("https://raw.github.com/wp-cli/builds/gh-pages/phar/wp-cli.phar", $bin, "wp-cli.phar");
			$output->writeln("<info>Downloaded wp-cli.phar to " . $bin . "</info>");
			try {
				$output->writeln("<comment>Setting permissions</comment>");

				$fs->chmod($bin . "/wp-cli.phar", 0555);
				$output->writeln($bin . "/wp-cli.phar permissions set to 0555");

				$fs->copy($bin . "/wp-cli.phar", $bin . "/wp");
				$fs->remove($bin . "/wp-cli.phar");

				$output->writeln($bin . "<info>moved: /wp-cli.phar -> " . $bin . "/wp</info>");
			} catch (IOExceptionInterface $e){
				$output->writeln("<error>An error occured while trying to chmod wp-cli.phar.</error>");
				return;
			}
			$output->writeln("<info>WordPress CLI installed successfully</info>");
		}

		// create folder
		$output->writeln("<comment>Creating WordPress install folder</comment>");
		try {
			$fs->mkdir($this->directory);
		} catch (IOExceptionInterface $e) {
			$output->writeln("<error>An error occurred while creating your directory at " . $e->getPath() . "</error>");
			return;
		}
		$output->writeln("<info>".getcwd() . "/" . $this->directory ." created successfully</info>");

		// setup wordpress
		if($this->setupWordPress($input, $output) !== false){

			// cleanup
			$this->postInstall($input, $output);

			$question = new ConfirmationQuestion("Would you like to include Advanced Custom Fields? ", true);
			if($helper->ask($input, $output, $question)){
				shell_exec("wp plugin install https://github.com/elliotcondon/acf/archive/master.zip  --activate  --path=" . $this->directory . " --quiet");
				$output->writeln("<info>Advanced Custom Fields has been installed</info>");
			}

			$question = new ConfirmationQuestion("Would you like to include Gravity Forms? ", true);
			if($helper->ask($input, $output, $question)){
				shell_exec("wp plugin install https://github.com/gravityforms/gravityforms/archive/master.zip  --activate --path=" . $this->directory . " --quiet");
				$output->writeln("<info>Gravity Forms has been installed</info>");
			}

			// ask if the user wants to set up .gitignore
			$question = new ConfirmationQuestion("Would you like me to include a .gitignore? ", false);
			if($helper->ask($input, $output, $question)){
				$this->setGitIgnore();
			}

			// alert ready to go
			$output->writeln("\r\n<info>Completed!</info>");
			$output->writeln("Browser will automatically open in 5 seconds. Press ctrl+c to exit.");
			shell_exec("sleep 5 && open http://localhost/" . $this->wp_folder . "/" . $this->directory . "/wp-admin");
		}else{
			return;
		}


	}

	private function download($url, $dir, $filename){
		$response = $this->client->get($url)->getBody();
		file_put_contents($dir . "/" . $filename, $response);

		return $this;
	}

	private function setupWordPress($input, $output){

		// cd into chosen directory and download wordpress
		chdir($this->directory);
		$helper = $this->getHelper("question");
		$question = new Question("Please enter your admin email address (default: admin@localhost) ", "admin@localhost.com");
		$question2 = new Question("Please enter your desired username (default: admin) ", "admin");
		$question3 = new Question("Please enter your desired password ", "admin");
		$question3->setHidden(true);
		$question3->setHiddenFallback(false);
		$question4 = new Question("Please enter your desired site title (default: My WordPress Site)", "My WordPress Site");

		$question5 = new ConfirmationQuestion("Have you configured your database yet? Y/N ", false);
		if(!$helper->ask($input, $output, $question5)){
			$question6 = new Question("Enter your desired database name ", "wordpress");
			$db_name = $helper->ask($input, $output, $question6);

			$output->writeln("<comment>Trying to create database '" . $db_name . "'</comment>");

			$servername = "127.0.0.1";
			$username = "root";
			$password = "root";

			// Create connection
			$conn = new \mysqli($servername, $username, $password);
			// Check connection
			if ($conn->connect_error) {
				$output->writeln("<error>Connection failed: " . $conn->connect_error . "</error>");
				return;
			}

			// Create database
			$sql = "CREATE DATABASE " . $db_name;
			if ($conn->query($sql) === TRUE) {
				$output->writeln("<info>Database created successfully</info>");
			} else {
				$output->writeln("<error>Error creating database: " . $conn->error . "</error>");
				return false;
			}

			$conn->close();
		}else{
			$question6 = new Question("Enter your database name ", "wordpress");
			$db_name = $helper->ask($input, $output, $question6);
		}

		$email = $helper->ask($input, $output, $question);
		$user = $helper->ask($input, $output, $question2);
		$pass = $helper->ask($input, $output, $question3);
		$title = $helper->ask($input, $output, $question4);
		$url = "http://localhost/" . $this->directory;

		$output->writeln("<comment>Fetching WordPress...</comment>");

		$out = shell_exec("wp core download --locale=en_GB --force=true --skip-plugins --skip-themes --color");
		$output->writeln($out);

		$output->writeln("<comment>Configuring WordPress...</comment>");

		$question7 = new Question("Please enter the name of your project folder ");
		$this->wp_folder = $helper->ask($input, $output, $question7);

		$out = shell_exec("wp core config --dbname=" . $db_name . " --dbuser=root --dbpass=root --dbhost=127.0.0.1");
		$output->writeln($out);

		$output->writeln("<comment>Installing WordPress</comment>");

		$out = shell_exec("wp core install --url='http://localhost/" . $this->wp_folder . "' --title='" . $title . "' --admin_user='" . $user . "' --admin_password='" . $pass . "' --admin_email='" . $email . "'");
		$output->writeln($out);

		shell_exec("wp option update home http://localhost/" . $this->wp_folder);
		shell_exec("wp option update siteurl http://localhost/" . $this->wp_folder . "/" . $this->directory);

		$question8 = new ConfirmationQuestion("Would you like to import a custom theme? ", false);
		if($helper->ask($input, $output, $question8)){
			$question9 = new Question("Enter a remote url, a path to a zip file or a url to a remote zip of your theme ", "https://github.com/roikles/Flexbones/archive/master.zip");
			$theme_url = $helper->ask($input, $output, $question9);

			shell_exec("wp theme install " . $theme_url . " --force --activate");

			$output->writeln("<info>Theme installed and activated successfully</info>");
		}else{
			shell_exec("wp scaffold _s theme --activate --theme_name='My Theme' --sassify");
		}

		shell_exec("wp theme delete twentythirteen twentyfourteen twentyfifteen");

		$output->writeln("<info>Completed WordPress Setup</info>");
		return $this;
	}

	private function postInstall($input, $output){
		chdir("../");

		$output->writeln("<comment>Cleaning up...</comment>");
		shell_exec("rm " . $this->directory . "/readme.html");
		shell_exec("mv " . $this->directory . "/wp-content wp-content");
		shell_exec("rm " . $this->directory . "/index.php && touch " . $this->directory . "/index.php && echo '<?php // Silence... ' >> " . $this->directory . "/index.php");
		shell_exec("echo '<?php define(\"WP_USE_THEMES\", true); require( dirname( __FILE__ ) . \"/" . $this->directory . "/wp-blog-header.php\" );' >> index.php");
		shell_exec("echo 'define(\"WP_DEBUG\", true); define(\"WP_CONTENT_DIR\", dirname(__FILE__) . \"/wp-content\" ); define(\"WP_CONTENT_URL\", \"http://localhost/" . $this->wp_folder . "/wp-content\");' >> " . $this->directory . "/wp-config.php");
		shell_exec("touch config.yml && rm config.yml && echo 'apache_modules:\r\n  -  mod_rewrite' >> config.yml");
		shell_exec("wp rewrite structure '/%postname%/' --hard --path=" . $this->directory);
		shell_exec("rm config.yml");
		shell_exec("wp site empty --yes --path=" . $this->directory);

		return $this;
	}

	private function setGitIgnore(){
		shell_exec("touch .gitignore && rm .gitignore && echo '.sass-cache\r\nReadme.md\r\nsrc\r\nvendor\r\ncomposer.json\r\ncomposer.lock\r\nconsole.php\r\n.DS_Store\r\n" . $this->directory . "' >> .gitignore");
	}
}