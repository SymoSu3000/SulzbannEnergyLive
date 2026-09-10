<?php

declare(strict_types=1);

class EnergyFlowLive extends IPSModule
{
    // ========================================================================
    // Fronius / Energie
    // ========================================================================

    private const ID_PV             = 24848;
    private const ID_GRID           = 36592;
    private const ID_BAT_CHARGE     = 21945;
    private const ID_BAT_DISCHARGE  = 50622;
    private const ID_SOC            = 46752;

    // Zusatzinformationen
    private const ID_TEMP_WR        = 29292;
    private const ID_TEMP_BAT_MAX   = 32466;
    private const ID_VOLTAGE        = 58820;
    private const ID_FREQUENCY      = 40528;

    // Wetter
    private const ID_WEATHER_ID     = 43256;
    private const ID_WEATHER_ICON   = 34540;
    private const ID_CLOUDS         = 14175;
    private const ID_RAIN_SENSOR    = 29096;


    public function Create(): void
    {
        parent::Create();

        $this->SetVisualizationType(1);
    }


    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegisterEnergyMessages();
    }


    // ========================================================================
    // HTML-SDK
    // ========================================================================

    public function GetVisualizationTile(): string
    {
        $htmlFile = __DIR__ . '/module.html';

        if (!file_exists($htmlFile)) {
            return '<div>module.html nicht gefunden</div>';
        }

        $html = file_get_contents($htmlFile);

        if ($html === false) {
            return '<div>module.html konnte nicht gelesen werden</div>';
        }

        return $html;
    }


    public function RequestAction($Ident, $Value): void
    {
        if ($Ident === 'Refresh') {
            $this->SendLiveValues();
            return;
        }

        throw new Exception('Ungültige Aktion: ' . $Ident);
    }


    // ========================================================================
    // Symcon Nachrichten
    // ========================================================================

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

        if ($Message === VM_UPDATE) {
            $this->SendLiveValues();
        }
    }


    private function RegisterEnergyMessages(): void
    {
        $ids = [
            self::ID_PV,
            self::ID_GRID,
            self::ID_BAT_CHARGE,
            self::ID_BAT_DISCHARGE,
            self::ID_SOC,

            self::ID_TEMP_WR,
            self::ID_TEMP_BAT_MAX,
            self::ID_VOLTAGE,
            self::ID_FREQUENCY,

            self::ID_WEATHER_ID,
            self::ID_WEATHER_ICON,
            self::ID_CLOUDS,
            self::ID_RAIN_SENSOR
        ];

        foreach ($ids as $id) {
            if (IPS_VariableExists($id)) {
                $this->RegisterMessage(
                    $id,
                    VM_UPDATE
                );
            }
        }
    }


    // ========================================================================
    // Livewerte
    // ========================================================================

    private function SendLiveValues(): void
    {
        $payload = $this->BuildPayload();

        $this->UpdateVisualizationValue(
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            )
        );
    }


    private function BuildPayload(): array
    {
        $pvRaw =
            $this->ReadFloat(
                self::ID_PV
            );

        $gridRaw =
            $this->ReadFloat(
                self::ID_GRID
            );

        $batChargeRaw =
            $this->ReadFloat(
                self::ID_BAT_CHARGE
            );

        $batDischargeRaw =
            $this->ReadFloat(
                self::ID_BAT_DISCHARGE
            );


        // ====================================================================
        // Netz
        //
        // #36592:
        // positiv = Netzbezug
        // negativ = Einspeisung
        // ====================================================================

        $gridImport =
            max(
                0.0,
                $gridRaw
            );

        $gridExport =
            max(
                0.0,
                -$gridRaw
            );


        // ====================================================================
        // Batterie
        //
        // Diagnose bestätigt:
        //
        // Laden:
        // #21945 positiv
        //
        // Entladen:
        // #50622 negativ
        // ====================================================================

        $batteryCharge =
            max(
                0.0,
                $batChargeRaw
            );

        $batteryDischarge =
            max(
                0.0,
                -$batDischargeRaw
            );


        // ====================================================================
        // Hausverbrauch
        //
        // Haus =
        // PV
        // + Netzbezug
        // + Batterieentladung
        // - Einspeisung
        // - Batterieladung
        // ====================================================================

        $house =
            $pvRaw
            +
            $gridImport
            +
            $batteryDischarge
            -
            $gridExport
            -
            $batteryCharge;

        $house =
            max(
                0.0,
                $house
            );


        // ====================================================================
        // Batterie-Zustand
        // ====================================================================

        if ($batteryCharge > 0.5) {
            $batteryState = 'charge';
        } elseif ($batteryDischarge > 0.5) {
            $batteryState = 'discharge';
        } else {
            $batteryState = 'idle';
        }


        // ====================================================================
        // Netz-Zustand
        // ====================================================================

        if ($gridImport > 0.5) {
            $gridState = 'import';
        } elseif ($gridExport > 0.5) {
            $gridState = 'export';
        } else {
            $gridState = 'idle';
        }


        return [
            'type' => 'energy',

            'timestamp' => time(),

            'pv' => $pvRaw,

            'gridRaw' => $gridRaw,
            'gridImport' => $gridImport,
            'gridExport' => $gridExport,
            'gridState' => $gridState,

            'batteryCharge' => $batteryCharge,
            'batteryDischarge' => $batteryDischarge,
            'batteryState' => $batteryState,

            'house' => $house,

            'soc' =>
                $this->ReadFloat(
                    self::ID_SOC
                ),

            'tempWR' =>
                $this->ReadFloat(
                    self::ID_TEMP_WR
                ),

            'tempBattery' =>
                $this->ReadFloat(
                    self::ID_TEMP_BAT_MAX
                ),

            'voltage' =>
                $this->ReadFloat(
                    self::ID_VOLTAGE
                ),

            'frequency' =>
                $this->ReadFloat(
                    self::ID_FREQUENCY
                ),

            'weatherID' =>
                $this->ReadInt(
                    self::ID_WEATHER_ID
                ),

            'weatherIcon' =>
                $this->ReadString(
                    self::ID_WEATHER_ICON
                ),

            'clouds' =>
                $this->ReadFloat(
                    self::ID_CLOUDS
                ),

            'rain' =>
                $this->ReadBool(
                    self::ID_RAIN_SENSOR
                )
        ];
    }


    // ========================================================================
    // Sichere Leser
    // ========================================================================

    private function ReadFloat(int $id): float
    {
        if (!IPS_VariableExists($id)) {
            return 0.0;
        }

        return floatval(
            GetValue($id)
        );
    }


    private function ReadInt(int $id): int
    {
        if (!IPS_VariableExists($id)) {
            return 0;
        }

        return intval(
            GetValue($id)
        );
    }


    private function ReadBool(int $id): bool
    {
        if (!IPS_VariableExists($id)) {
            return false;
        }

        return boolval(
            GetValue($id)
        );
    }


    private function ReadString(int $id): string
    {
        if (!IPS_VariableExists($id)) {
            return '';
        }

        return strval(
            GetValue($id)
        );
    }
}
