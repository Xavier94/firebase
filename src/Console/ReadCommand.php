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
	private $_data;
	private $_ch;
	private $_smoney_url;
	private $_smoney_token;
	private $_smoney_action_payment;

	protected function configure()
	{
		$this
			->setName('fire:read')
			->setDescription('Read firebase')
			->addOption(
				'path',
				'p',
				InputOption::VALUE_REQUIRED,
				'Firebase App INI Path filename'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$path_filename = $input->getOption('path');
		if ($path_filename == '')
		{
			$output->writeln('<error>Path filename empty</error>');
			return -1;
		}

		$ini_file = parse_ini_file($path_filename, true);
		if ($ini_file === false)
		{
			$output->writeln('<error>Config file not exist</error>');
			return -1;
		}

		$this->_url   = $ini_file['firebase']['url'];
		$this->_token = $ini_file['firebase']['token'];
		$this->_path  = '/users';
		$this->_smoney_url = $ini_file['smoney']['url'];
		$this->_smoney_token = $ini_file['smoney']['token'];
		$this->_smoney_action_payment = $ini_file['smoney']['action_payment'];

		$this->_firebase = new \Firebase\FirebaseLib($this->_url, $this->_token);
		$this->_data     = json_decode($this->_firebase->get($this->_path), true);

		$this->performMoney($output);
	}

	protected function performMoney($output)
	{
		$this->_ch = curl_init();
		curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, false);

		$header = array(
			'Accept: application/vnd.s-money.v1+json',
			'Authorization: Bearer ' . $this->_smoney_token,
			'Content-Type: application/vnd.s-money.v1+json',
			);
		curl_setopt($this->_ch, CURLOPT_HTTPHEADER, $header);

		if ($output->isDebug())
		{
			// Return http header + html
			curl_setopt($this->_ch, CURLOPT_HEADER, true);
			$fp = fopen('debug.txt', 'w');
			curl_setopt($this->_ch, CURLOPT_VERBOSE, 1);
			curl_setopt($this->_ch, CURLOPT_STDERR, $fp);
		}

		foreach ($this->_data as $account_name => $account)
		{
			$output->writeln('cardID: ' . $account['cardID'], OutputInterface::VERBOSITY_VERBOSE);
			$output->writeln('Count sub account: ' . count($account['subaccounts']), OutputInterface::VERBOSITY_VERBOSE);

			$smoney_action = sprintf($this->_smoney_action_payment, $account_name);
			$output->writeln($this->_smoney_url . $smoney_action, OutputInterface::VERBOSITY_VERBOSE);

			foreach ($account['subaccounts'] as $subaccount_name => $subaccount)
			{
				if (!isset($subaccount['totalAmount']))
				{
					continue;
				}

				$output->writeln('Amount: ' . $subaccount['totalAmount'], OutputInterface::VERBOSITY_VERBOSE);

				// POST DATA
				$postfields = array(
					'OrderId' => 'MrBank money purge :) - ' . date('Y-m-d') . ' - ' . rand(0, 500000),
					'AccountId' => array('appAccountId' => $subaccount_name),
					'Card' => array('appCardId' => $account['cardID']),
					'IsMine' => true,
					'Amount' => 100 * 100.00,
				);
				curl_setopt($this->_ch, CURLOPT_POST, true);
				curl_setopt($this->_ch, CURLOPT_POSTFIELDS, json_encode($postfields));

				curl_setopt($this->_ch, CURLOPT_URL, $this->_smoney_url . $smoney_action);

				$smoney_response = curl_exec($this->_ch);

				if ($smoney_response === false)
				{
					$output->writeln('<error>Curl ' . curl_error($this->_ch) . '</error>');
					return -1;
				}

				$http_code = curl_getinfo($this->_ch, CURLINFO_HTTP_CODE);
				if ($http_code == 404)
				{
					$output->writeln('<error>Curl HTTP 404</error>');
					return -1;
				}

				$smoney_response = json_decode($smoney_response);
				var_dump($smoney_response);
			}
		}
	}
}
