<?php

declare(strict_types=1);

class EnergyFlowLive extends IPSModule
{
    /*
     * =====================================================
     * Kernwerte Energie
     * =====================================================
     */

    private const ID_PV_W = 24848;
    private const ID_GRID_W = 36592;

    private const ID_BAT_CHARGE_W = 21945;
    private const ID_BAT_DISCHARGE_W = 50622;
    private const ID_SOC = 46752;


    /*
     * =====================================================
     * Zusatzwerte
     * =====================================================
     */

    private const ID_TEMP_WR = 29292;
    private const ID_TEMP_BAT = 35042;

    private const ID_GRID_VOLTAGE = 58820;
    private const ID_GRID_FREQUENCY = 40528;

    private const ID_WEATHER_CONDITION = 43256;
    private const ID_WEATHER_ICON = 34540;

    /*
     * Richtige BOOL-Variable unter KNX-Instanz #55641
     */
    private const ID_RAIN_SENSOR = 29096;


    public function Create(): void
    {
        parent::Create();

        $this->SetVisualizationType(1);
    }


    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        /*
         * Nur vorhandene Variablen abonnieren.
         *
         * Eine fehlende optionale Wettervariable
         * darf das Modul nicht mehr blockieren.
         */
        foreach ($this->GetObservedVariableIDs() as $variableID) {

            if (IPS_VariableExists($variableID)) {

                $this->RegisterMessage(
                    $variableID,
                    VM_UPDATE
                );
            }
        }
    }


    public function GetVisualizationTile(): string
    {
        return file_get_contents(
            __DIR__ . '/module.html'
        );
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


    public function RequestAction(
        $Ident,
        $Value
    ): void {
        if ($Ident === 'Refresh') {

            $this->SendLiveValues();

            return;
        }


        throw new Exception(
            'Invalid Ident'
        );
    }


    /*
     * =====================================================
     * Alle beobachteten Variablen
     * =====================================================
     */

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
            self::ID_GRID_FREQUENCY,

            self::ID_WEATHER_CONDITION,
            self::ID_WEATHER_ICON,

            self::ID_RAIN_SENSOR
        ];
    }


    /*
     * =====================================================
     * Sichere Hilfsfunktionen
     * =====================================================
     */

    private function SafeFloat(
        int $variableID,
        float $default = 0.0
    ): float {
        if (!IPS_VariableExists($variableID)) {
            return $default;
        }

        return (float) GetValue($variableID);
    }


    private function SafeInteger(
        int $variableID,
        int $default = 0
    ): int {
        if (!IPS_VariableExists($variableID)) {
            return $default;
        }

        return (int) GetValue($variableID);
    }


    private function SafeString(
        int $variableID,
        string $default = ''
    ): string {
        if (!IPS_VariableExists($variableID)) {
            return $default;
        }

        return (string) GetValue($variableID);
    }


    private function SafeBool(
        int $variableID,
        bool $default = false
    ): bool {
        if (!IPS_VariableExists($variableID)) {
            return $default;
        }

        return (bool) GetValue($variableID);
    }


    /*
     * =====================================================
     * Live-Werte senden
     * =====================================================
     */

    private function SendLiveValues(): void
    {
        /*
         * Nur diese fünf Variablen sind wirklich notwendig.
         */

        $requiredIDs = [
            self::ID_PV_W,
            self::ID_GRID_W,
            self::ID_BAT_CHARGE_W,
            self::ID_BAT_DISCHARGE_W,
            self::ID_SOC
        ];


        foreach ($requiredIDs as $variableID) {

            if (!IPS_VariableExists($variableID)) {

                /*
                 * Nur wenn ein echter Energie-Kernwert
                 * fehlt, können wir keine sinnvolle
                 * Energieverteilung berechnen.
                 */

                return;
            }
        }


        /*
         * =================================================
         * PV
         * =================================================
         */

        $pvKW = max(
            0.0,
            $this->SafeFloat(
                self::ID_PV_W
            ) / 1000.0
        );


        /*
         * =================================================
         * Netz
         *
         * positiv = Bezug
         * negativ = Einspeisung
         * =================================================
         */

        $gridW =
            $this->SafeFloat(
                self::ID_GRID_W
            );


        $gridImportKW = max(
            0.0,
            $gridW / 1000.0
        );


        $gridExportKW = max(
            0.0,
            -$gridW / 1000.0
        );


        /*
         * =================================================
         * Batterie Laden
         * =================================================
         */

        $batteryChargeKW = max(
            0.0,
            $this->SafeFloat(
                self::ID_BAT_CHARGE_W
            ) / 1000.0
        );


        /*
         * =================================================
         * Batterie Entladen
         *
         * Fronius liefert den Wert negativ.
         * =================================================
         */

        $batteryDischargeRawW =
            $this->SafeFloat(
                self::ID_BAT_DISCHARGE_W
            );


        $batteryDischargeKW = max(
            0.0,
            -$batteryDischargeRawW / 1000.0
        );


        /*
         * =================================================
         * SOC
         * =================================================
         */

        $soc = max(
            0.0,
            min(
                100.0,
                $this->SafeFloat(
                    self::ID_SOC
                )
            )
        );


        /*
         * =================================================
         * Zusatzwerte
         *
         * Ab hier ist NICHTS mehr zwingend erforderlich.
         * =================================================
         */

        $tempWR =
            $this->SafeFloat(
                self::ID_TEMP_WR
            );


        $tempBattery =
            $this->SafeFloat(
                self::ID_TEMP_BAT
            );


        $gridVoltage =
            $this->SafeFloat(
                self::ID_GRID_VOLTAGE
            );


        $gridFrequency =
            $this->SafeFloat(
                self::ID_GRID_FREQUENCY
            );


        $conditionID =
            $this->SafeInteger(
                self::ID_WEATHER_CONDITION,
                800
            );


        $conditionIcon =
            $this->SafeString(
                self::ID_WEATHER_ICON,
                ''
            );


        $rainDetected =
            $this->SafeBool(
                self::ID_RAIN_SENSOR,
                false
            );


        /*
         * =================================================
         * Verbrauch berechnen
         * =================================================
         */

        $houseKW =
            $pvKW
            +
            $gridImportKW
            +
            $batteryDischargeKW
            -
            $gridExportKW
            -
            $batteryChargeKW;


        $houseKW = max(
            0.0,
            $houseKW
        );


        /*
         * =================================================
         * Payload
         * =================================================
         */

        $payload = [

            'pv' =>
                round(
                    $pvKW,
                    3
                ),


            'house' =>
                round(
                    $houseKW,
                    3
                ),


            'gridImport' =>
                round(
                    $gridImportKW,
                    3
                ),


            'gridExport' =>
                round(
                    $gridExportKW,
                    3
                ),


            'batteryCharge' =>
                round(
                    $batteryChargeKW,
                    3
                ),


            'batteryDischarge' =>
                round(
                    $batteryDischargeKW,
                    3
                ),


            'soc' =>
                round(
                    $soc,
                    1
                ),


            'tempWR' =>
                round(
                    $tempWR,
                    1
                ),


            'tempBattery' =>
                round(
                    $tempBattery,
                    1
                ),


            'gridVoltage' =>
                round(
                    $gridVoltage,
                    1
                ),


            'gridFrequency' =>
                round(
                    $gridFrequency,
                    1
                ),


            'weatherConditionID' =>
                $conditionID,


            'weatherIcon' =>
                $conditionIcon,


            'rainDetected' =>
                $rainDetected
        ];


        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
            |
            JSON_UNESCAPED_SLASHES
        );


        if ($json === false) {
            return;
        }


        $this->UpdateVisualizationValue(
            $json
        );
    }
}
