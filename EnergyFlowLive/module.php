<?php

class EnergyFlowLive extends IPSModule
{
    private const ID_PV_W       = 24848;
    private const ID_GRID_W     = 36592;
    private const ID_BAT_CHARGE = 21945;
    private const ID_BAT_DISCH  = 50622;
    private const ID_SOC        = 46752;

    public function Create()
    {
        parent::Create();

        // Eigene HTML-Kacheldarstellung aktivieren
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Live-Werte abonnieren
        $this->RegisterMessage(self::ID_PV_W, VM_UPDATE);
        $this->RegisterMessage(self::ID_GRID_W, VM_UPDATE);
        $this->RegisterMessage(self::ID_BAT_CHARGE, VM_UPDATE);
        $this->RegisterMessage(self::ID_BAT_DISCH, VM_UPDATE);
        $this->RegisterMessage(self::ID_SOC, VM_UPDATE);
    }

    public function GetVisualizationTile()
    {
        return file_get_contents(__DIR__ . '/module.html');
    }

    public function MessageSink(
        $TimeStamp,
        $SenderID,
        $Message,
        $Data
    ) {
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

    private function SendLiveValues()
    {
        if (
            !IPS_VariableExists(self::ID_PV_W) ||
            !IPS_VariableExists(self::ID_GRID_W) ||
            !IPS_VariableExists(self::ID_BAT_CHARGE) ||
            !IPS_VariableExists(self::ID_BAT_DISCH) ||
            !IPS_VariableExists(self::ID_SOC)
        ) {
            return;
        }

        /*
         * PV-Leistung
         * Variable liefert Watt
         */
        $pvKW = max(
            0,
            floatval(
                GetValue(self::ID_PV_W)
            ) / 1000
        );

        /*
         * Netzleistung
         *
         * positiv  = Netzbezug
         * negativ  = Einspeisung
         */
        $gridW = floatval(
            GetValue(self::ID_GRID_W)
        );

        $gridImportKW = max(
            0,
            $gridW / 1000
        );

        $gridExportKW = max(
            0,
            -$gridW / 1000
        );

        /*
         * Batterie laden
         */
        $batteryChargeKW = max(
            0,
            floatval(
                GetValue(self::ID_BAT_CHARGE)
            ) / 1000
        );

        /*
         * Batterie entladen
         *
         * Bei deiner Fronius-Variable wurde
         * Entladung als negativer Wert beobachtet.
         */
        $rawDischarge = floatval(
            GetValue(self::ID_BAT_DISCH)
        );

        $batteryDischargeKW = abs(
            min(
                0,
                $rawDischarge
            )
        ) / 1000;

        /*
         * Ladezustand
         */
        $soc = max(
            0,
            min(
                100,
                floatval(
                    GetValue(self::ID_SOC)
                )
            )
        );

        /*
         * Hausverbrauch
         *
         * Haus =
         * PV
         * + Netzbezug
         * + Batterieentladung
         * - Netzeinspeisung
         * - Batterieladung
         */
        $houseKW = max(
            0,
            $pvKW
            + $gridImportKW
            + $batteryDischargeKW
            - $gridExportKW
            - $batteryChargeKW
        );

        /*
         * Daten für die Visualisierung
         */
        $values = [
            'pv' => round(
                $pvKW,
                2
            ),

            'house' => round(
                $houseKW,
                2
            ),

            'gridImport' => round(
                $gridImportKW,
                2
            ),

            'gridExport' => round(
                $gridExportKW,
                2
            ),

            'batteryCharge' => round(
                $batteryChargeKW,
                2
            ),

            'batteryDischarge' => round(
                $batteryDischargeKW,
                2
            ),

            'soc' => round(
                $soc,
                1
            )
        ];

        /*
         * HTML SDK erwartet hier einen String.
         * Deshalb Array als JSON übertragen.
         */
        $this->UpdateVisualizationValue(
            json_encode(
                $values,
                JSON_UNESCAPED_UNICODE
            )
        );
    }

    public function RequestAction(
        $Ident,
        $Value
    ) {
        switch ($Ident) {
            case 'Refresh':
                $this->SendLiveValues();
                break;

            default:
                throw new Exception(
                    'Unbekannte Aktion: '
                    . $Ident
                );
        }
    }
}
