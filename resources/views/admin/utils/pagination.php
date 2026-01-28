<?php

// currentpage = 1 && totalresults = 154

// we will use default one ok

class Pagination
{
    public $currentPage; // for current page ex first page 1
    public $totalResults; // how many results in db ex 32 images data in db
    public $totalPage; // this is based on totalResults/limit ex 100 totalresults & limit 10 so totalpage 100/10 = 10 totalpages
    public $limit; // how many items can be showed on each page
    public $gap;
    public $offset; // tell how many items we have to skip to make appropiate results

    public function __construct($currentPage, $totalResults, $limit, $gap = 3)
    {
        $this->currentPage = $currentPage; // 1
        $this->totalResults = $totalResults; // 32
        $this->limit = $limit; // 10
        $this->totalPage = ceil($totalResults / $limit); // 4
        $this->gap = $gap; // 3
        $this->offset = ($currentPage - 1) * $limit; // 0
    }




public function paginate()
{
    // Previous button
    if ($this->currentPage > 1) {
        echo "<a href='?page=" . ($this->currentPage - 1) . "&offset=0' class='page-btn'><i class='ti ti-arrow-left'></i></a>";
    }

    // Calculate the range of pages to show around current page
    $start = max(1, $this->currentPage - floor($this->gap / 2));
    $end = min($this->totalPage, $start + $this->gap - 1);
    $start = max(1, $end - $this->gap + 1); // Readjust start if end was limited

    // Show page 1 + ellipsis if not in main range
    if ($start > 1) {
        echo "<a href='?page=1&offset=0' class='page-btn'>1</a>";
        if ($start > 2) {
            echo "<span class='page-btn'>...</span>";
        }
    }

    // Main page links
    for ($i = $start; $i <= $end; $i++) {
        $class = ($i == $this->currentPage) ? 'page-btn current' : 'page-btn';
        echo "<a href='?page=$i&offset=" . (($i - 1) * $this->limit) . "' class='$class'>$i</a>";
    }

    // Show ellipsis + last page if not in main range
    if ($end < $this->totalPage) {
        if ($end < $this->totalPage - 1) {
            echo "<span class='page-btn'>...</span>";
        }
        echo "<a href='?page=" . $this->totalPage . "&offset=" . (($this->totalPage - 1) * $this->limit) . "' class='page-btn'>" . $this->totalPage . "</a>";
    }

    // Next button
    if ($this->currentPage < $this->totalPage) {
        echo "<a href='?page=" . ($this->currentPage + 1) . "&offset=" . ($this->currentPage * $this->limit) . "' class='page-btn'><i class='ti ti-arrow-right'></i> Next</a>";
    }
}
}