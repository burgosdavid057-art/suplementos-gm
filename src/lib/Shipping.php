<?php
declare(strict_types=1);

/**
 * Catálogo de métodos de envío de Suplementos GM (Rionegro, Antioquia).
 *
 *   1) interrapidisimo   PCE — pago contra entrega, $0 al checkout, lo cobra
 *                              el mensajero al recibir. 2-5 días hábiles.
 *   2) bus               $15.000 — encomienda en bus, el cliente recoge en
 *                              Terminal del Norte o Terminal del Sur de
 *                              Medellín. 1-2 días hábiles.
 *   3) oriente_cercano   $10.000 — entrega local en oriente antioqueño
 *                              (Rionegro y aledaños). Mismo día o siguiente.
 */
class Shipping {
    private const METHODS = [
        [
            'id'                => 'interrapidisimo',
            'name'              => 'InterRapidísimo',
            'tagline'           => 'Pago contra entrega',
            'description'       => 'Te lo enviamos por InterRapidísimo a tu dirección. El costo del envío se lo pagas al mensajero cuando recibas el pedido.',
            'cost'              => 0,
            'cost_label'        => 'Pago al recibir',
            'icon'              => 'truck',
            'eta_min_days'      => 2,
            'eta_max_days'      => 5,
            'eta_label'         => '2 a 5 días hábiles',
            'requires_address'  => true,
            'requires_terminal' => false,
        ],
        [
            'id'                => 'bus',
            'name'              => 'Envío por bus',
            'tagline'           => 'Recoges en terminal de Medellín',
            'description'       => 'Lo despachamos por encomienda. Lo recoges en la Terminal del Norte o del Sur de Medellín presentando tu cédula.',
            'cost'              => 15000,
            'cost_label'        => '$15.000',
            'icon'              => 'bus',
            'eta_min_days'      => 1,
            'eta_max_days'      => 2,
            'eta_label'         => '1 a 2 días hábiles',
            'requires_address'  => false,
            'requires_terminal' => true,
        ],
        [
            'id'                => 'oriente_cercano',
            'name'              => 'Oriente cercano',
            'tagline'           => 'Rionegro y aledaños',
            'description'       => 'Entrega local en oriente antioqueño cercano: Rionegro, La Ceja, El Carmen, El Retiro, Llanogrande.',
            'cost'              => 10000,
            'cost_label'        => '$10.000',
            'icon'              => 'map-pin',
            'eta_min_days'      => 0,
            'eta_max_days'      => 1,
            'eta_label'         => 'Mismo día o siguiente',
            'requires_address'  => true,
            'requires_terminal' => false,
        ],
    ];

    private const TERMINALS = [
        'norte' => 'Terminal del Norte (Medellín)',
        'sur'   => 'Terminal del Sur (Medellín)',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function methods(): array {
        return self::METHODS;
    }

    public static function methodById(string $id): ?array {
        foreach (self::METHODS as $m) {
            if ($m['id'] === $id) return $m;
        }
        return null;
    }

    public static function defaultMethodId(): string {
        return self::METHODS[0]['id'];
    }

    /** Costo de envío para un método (0 si es PCE o método inválido). */
    public static function quote(string $methodId): int {
        $m = self::methodById($methodId);
        return $m ? (int)$m['cost'] : 0;
    }

    /** Min y máx para mostrar rango en el carrito. */
    public static function minRate(): int {
        return min(array_column(self::METHODS, 'cost'));
    }
    public static function maxRate(): int {
        return max(array_column(self::METHODS, 'cost'));
    }

    /** @return array<string, string>  ['norte' => 'Terminal del Norte (Medellín)', ...] */
    public static function terminals(): array {
        return self::TERMINALS;
    }

    public static function terminalLabel(string $key): ?string {
        return self::TERMINALS[$key] ?? null;
    }

    /**
     * Devuelve un texto legible con la fecha estimada de entrega.
     * Ej: "Entre el lunes 11 y el viernes 15 de mayo"
     */
    public static function etaText(string $methodId, ?string $orderDate = null): ?string {
        $m = self::methodById($methodId);
        if (!$m) return null;
        $start = self::addBusinessDays($orderDate, (int)$m['eta_min_days']);
        $end   = self::addBusinessDays($orderDate, (int)$m['eta_max_days']);

        $fmt = static function (DateTimeImmutable $d): string {
            // Días en español
            $dias = ['Sunday'=>'domingo','Monday'=>'lunes','Tuesday'=>'martes',
                     'Wednesday'=>'miércoles','Thursday'=>'jueves','Friday'=>'viernes','Saturday'=>'sábado'];
            $meses = ['January'=>'enero','February'=>'febrero','March'=>'marzo','April'=>'abril',
                      'May'=>'mayo','June'=>'junio','July'=>'julio','August'=>'agosto',
                      'September'=>'septiembre','October'=>'octubre','November'=>'noviembre','December'=>'diciembre'];
            $diaSemana = $dias[$d->format('l')];
            $mes = $meses[$d->format('F')];
            return $diaSemana . ' ' . (int)$d->format('d') . ' de ' . $mes;
        };

        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            return 'Entrega estimada: ' . $fmt($start);
        }
        return 'Entrega estimada: entre el ' . $fmt($start) . ' y el ' . $fmt($end);
    }

    /**
     * Suma N días hábiles a una fecha (saltando sábados y domingos).
     * Si N=0, devuelve el mismo día.
     */
    private static function addBusinessDays(?string $from, int $days): DateTimeImmutable {
        $start = $from
            ? new DateTimeImmutable($from, new DateTimeZone('America/Bogota'))
            : new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        if ($days <= 0) return $start;

        $current = $start;
        $added = 0;
        while ($added < $days) {
            $current = $current->modify('+1 day');
            $dow = (int)$current->format('N'); // 1 = lunes, 7 = domingo
            if ($dow >= 6) continue;            // saltar sábado/domingo
            $added++;
        }
        return $current;
    }
}
