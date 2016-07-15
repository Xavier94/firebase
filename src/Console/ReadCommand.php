<?php
namespace Fire\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use \Firebase\JWT\JWT;

class ReadCommand extends Command
{
	private $_url;
	private $_token;
	private $_path;
	private $_firebase;

	protected function configure()
	{
		$this
			->setName('fire:read')
			->setDescription('Read firebase')
			->addOption(
				'url',
				null,
				InputOption::VALUE_REQUIRED,
				'Url Firebase bdd'
			)
			->addOption(
				'token',
				null,
				InputOption::VALUE_REQUIRED,
				'Token Firebase app'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->_url   = $input->getOption('url');
		$this->_token = $input->getOption('token');
		$this->_path  = '/';

		$this->_firebase = new \Firebase\FirebaseLib($this->_url, $this->_token);
		$data            = json_decode($this->_firebase->get($this->_path), true);
		var_dump($data);
		//$output->writeln($data);
	}
}
