<?php

declare(strict_types=1);

class EnergyFlowLive extends IPSModule
{
    private const ID_PV_W = 24848;
    private const ID_GRID_W = 36592;
    private const ID_BAT_CHARGE_W = 21945;
    private const ID_BAT_DISCHARGE_W = 50622;
    private const ID_SOC = 46752;

    private const ID_TEMP_WR = 29292;
    private const ID_TEMP_BAT = 35042;
    private const ID_GRID_VOLTAGE = 58820;
    private const ID_GRID_FREQUENCY = 40528;

    public function Create(): void
    {
        parent::Create();

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        foreach ($this->GetObservedVariableIDs() as $variableID) {
            if (IPS_VariableExists($variableID)) {
                $this->RegisterMessage($variableID, VM_UPDATE);
            }
        }
    }

    public function GetVisualizationTile(): string
    {
        return file_get_contents(__DIR__ . '/module.html');
    }

    public function MessageSink(
        $TimeStamp,
        $SenderID,
        $Message,
        $Data
    ): void {
        parent::MessageSink(
            $TimeStamp,
            $SenderID,
            $Message,
            $Data
        );

        if ($Message !== VM_UPDATE) {
            return;
        }

        $this->SendLiveValues();
    }

    public function RequestAction($Ident, $Value): void
    {
        if ($Ident === 'Refresh') {
            $this->SendLiveValues();
            return;
        }

        throw new Exception('Invalid Ident');
    }

    private function GetObservedVariableIDs(): array
    {
        return [
            self::ID_PV_W,
            self::ID_GRID_W,
            self::ID_BAT_CHARGE_W,
            self::ID_BAT_DISCHARGE_W,
            self::ID_SOC,
            self::ID_TEMP_WR,
            self::ID_TEMP_BAT,
            self::ID_GRID_VOLTAGE,
            self::ID_GRID_FREQUENCY
        ];
    }

    private function SendLiveValues(): void
    {
        foreach ($this->GetObservedVariableIDs() as $variableID) {
            if (!IPS_VariableExists($variableID)) {
                return;
            }
        }

        $pvKW = max(
            0.0,
            (float) GetValue(self::ID_PV_W) / 1000.0
        );

        /*
         * Fronius Smart Meter:
         * positiv = Netzbezug
         * negativ = Einspeisung
         */
        $gridW = (float) GetValue(self::ID_GRID_W);

        $gridImportKW = max(
            0.0,
            $gridW / 1000.0
        );

        $gridExportKW = max(
            0.0,
            -$gridW / 1000.0
        );

        $batteryChargeKW = max(
            0.0,
            (float) GetValue(self::ID_BAT_CHARGE_W) / 1000.0
        );

        /*
         * Dieser Fronius-Wert ist beim Entladen negativ.
         */
        $batteryDischargeRawW =
            (float) GetValue(self::ID_BAT_DISCHARGE_W);

        $batteryDischargeKW = max(
            0.0,
            -$batteryDischargeRawW / 1000.0
        );

        $soc = max(
            0.0,
            min(
                100.0,
                (float) GetValue(self::ID_SOC)
            )
        );

        $tempWR =
            (float) GetValue(self::ID_TEMP_WR);

        $tempBattery =
            (float) GetValue(self::ID_TEMP_BAT);

        $gridVoltage =
            (float) GetValue(self::ID_GRID_VOLTAGE);

        $gridFrequency =
            (float) GetValue(self::ID_GRID_FREQUENCY);

        $houseKW =
            $pvKW
            + $gridImportKW
            + $batteryDischargeKW
            - $gridExportKW
            - $batteryChargeKW;

        $houseKW = max(
            0.0,
            $houseKW
        );

        $payload = [
            'pv'               => round($pvKW, 3),
            'house'            => round($houseKW, 3),
            'gridImport'       => round($gridImportKW, 3),
            'gridExport'       => round($gridExportKW, 3),
            'batteryCharge'    => round($batteryChargeKW, 3),
            'batteryDischarge' => round($batteryDischargeKW, 3),
            'soc'              => round($soc, 1),
            'tempWR'           => round($tempWR, 1),
            'tempBattery'      => round($tempBattery, 1),
            'gridVoltage'      => round($gridVoltage, 1),
            'gridFrequency'    => round($gridFrequency, 1)
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            return;
        }

        $this->UpdateVisualizationValue($json);
    }
}
