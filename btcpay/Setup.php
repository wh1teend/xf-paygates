<?php

namespace WH1\PaygateBtcPay;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1(): void
	{
		$db = $this->db();

		$db->insert('xf_payment_provider', [
			'provider_id'    => "wh1BtcPay",
			'provider_class' => "WH1\\PaygateBtcPay:BtcPay",
			'addon_id'       => "WH1/PaygateBtcPay"
		]);
	}

	public function uninstallStep1(): void
	{
		$db = $this->db();

		$db->delete('xf_payment_profile', "provider_id = 'wh1BtcPay'");
		$db->delete('xf_payment_provider', "provider_id = 'wh1BtcPay'");
	}
}