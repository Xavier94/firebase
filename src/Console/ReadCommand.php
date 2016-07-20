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

	/**
	 * Configure CLI batch
	 */
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

	/**
	 * 
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
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

	/**
	 * 
	 * @param $output
	 * @return int
	 */
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
				if (!isset($subaccount['totalAmount'])
				    || !isset($subaccount['creationDate'])
				    || !isset($subaccount['finishDate'])
				    || !isset($subaccount['iterate'])
					|| !isset($subaccount['iterateAmount'])
					|| !isset($subaccount['lastAmount']))
				{
					continue;
				}

				$create_date = new \DateTime($subaccount['creationDate']);
				$current_date = new \DateTime();
				$finish_date = new \DateTime($subaccount['finishDate']);
				if ($current_date < $create_date || $current_date > $finish_date)
				{
					continue;
				}
				
				//$d_create = new \DateTime($subaccount['creationDate']);
				//$d_finish = new \DateTime($subaccount['finishDate']);
				//$diff = $d_finish->diff($d_create);
				//print_r($diff);
				//continue;
				
				$output->writeln('Amount: ' . $subaccount['totalAmount'], OutputInterface::VERBOSITY_VERBOSE);
				$output->writeln('IterateAmount: ' . $subaccount['iterateAmount'], OutputInterface::VERBOSITY_VERBOSE);
				$output->writeln('Iterate: ' . $subaccount['iterate'], OutputInterface::VERBOSITY_VERBOSE);

				if ($subaccount['iterate'] != 0)
				{
					$amount = $subaccount['iterateAmount'];
				}
				else 
				{
					$amount = $subaccount['lastAmount'];
				}
				
				// POST DATA
				$postfields = array(
					'OrderId' => 'MrBank money purge :) - ' . date('Y-m-d') . ' - ' . rand(0, 500000),
					'AccountId' => array('appAccountId' => $subaccount_name),
					'Card' => array('appCardId' => $account['cardID']),
					'IsMine' => true,
					'Amount' => $amount * 100.00,
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

				$smoney_response = json_decode($smoney_response, true);
				
				if (array_key_exists('ErrorMessage', $smoney_response))
				{
					$output->writeln('<error>SMoney Error: ' . $smoney_response['ErrorMessage'] . '</error>');
				}
				else 
				{
					$path_update = '/users/' . $account_name . '/subaccounts/' . $subaccount_name;
					$data_update = array(
						'iterate' => (int)$subaccount['iterate'] - 1,
					);

					$firebase_response = $this->_firebase->update($path_update, $data_update);
				}
			}
		}
	}
}
