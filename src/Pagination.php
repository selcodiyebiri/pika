<?php
namespace Pika;

/**
 * Usage:
 *   $p = new Pagination($total, 20);
 *   $rows = DB::get("SELECT ... LIMIT {$p->limit} OFFSET {$p->offset}");
 *   echo $p->render();
 */
class Pagination
{
    public int $pages;
    public int $current;
    public int $offset;

    public function __construct(
        public int $total,
        public int $limit = 10,
        public string $param = 'page',
        public int $scroll = 5,
        public ?string $baseUrl = null,
    ) {
        $this->limit = max(1, $limit);
        $this->scroll = max(1, $scroll);
        $this->pages = max(1, (int) ceil($total / $this->limit));

        $page = $_GET[$param] ?? 1;
        $page = is_numeric($page) ? (int) $page : 1;
        $this->current = min(max(1, $page), $this->pages);
        $this->offset = ($this->current - 1) * $this->limit;
    }

    private function url(int $page): string
    {
        $base = $this->baseUrl ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $query = array_merge(is_array($_GET) ? $_GET : [], [$this->param => $page]);
        return htmlspecialchars($base . '?' . http_build_query($query), ENT_QUOTES);
    }

    private function item(string $label, ?int $page, bool $active = false): string
    {
        $class = 'page-item' . ($active ? ' active' : '') . ($page === null ? ' disabled' : '');
        $href = $page === null ? '#' : $this->url($page);
        return '<li class="' . $class . '"><a class="page-link" href="' . $href . '">' . $label . '</a></li>';
    }

    public function render(): string
    {
        if ($this->pages <= 1) return '';

        $start = (int) (floor(($this->current - 1) / $this->scroll) * $this->scroll) + 1;
        $end = min($start + $this->scroll - 1, $this->pages);
        $first = $this->current > 1;
        $last = $this->current < $this->pages;

        $html = $this->item('&laquo;', $first ? 1 : null)
              . $this->item('&lsaquo;', $first ? $this->current - 1 : null);
        for ($i = $start; $i <= $end; $i++) {
            $html .= $this->item((string) $i, $i, $i === $this->current);
        }
        $html .= $this->item('&rsaquo;', $last ? $this->current + 1 : null)
               . $this->item('&raquo;', $last ? $this->pages : null);

        return '<ul class="pagination">' . $html . '</ul>';
    }
}
