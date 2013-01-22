<?php  
  
class CsvHelper extends AppHelper { 
     
    var $enclosure = '"'; 
    var $filename = 'export'; 
    var $line = array(); 
    var $buffer; 
    var $csv_separator = csv_separator;
    static $nodes_info = null;
    static $structures = null;
     
    function CsvHelper() { 
        $this->clear(); 
    } 
     
    function clear() { 
        $this->line = array(); 
        $this->buffer = fopen('php://temp/maxmemory:'. (5*1024*1024), 'r+'); 
    } 
     
    function addField($value) { 
        $this->line[] = $value; 
    } 
     
    function endRow() { 
        $this->addRow($this->line); 
        $this->line = array(); 
    } 
     
    function addRow($row) { 
        fputcsv($this->buffer, $row, $this->csv_separator, $this->enclosure); 
    } 
     
    function renderHeaders() { 
		header ( "Content-Type: application/force-download" ); 
		header ( "Content-Type: application/octet-stream" ); 
		header ( "Content-Type: application/download" ); 
		header ( "Content-Type: text/csv" ); 
		header("Content-disposition:attachment;filename=".$this->filename.'_'.date('YMd_Hi').'.csv'); 
    } 
     
    function setFilename($filename) { 
        $this->filename = $filename; 
        /*
        if (strtolower(substr($this->filename, -4)) != '.csv') { 
            $this->filename .= '.csv'; 
        } 
        */
    } 
     
    function render($outputHeaders = true, $to_encoding = null, $from_encoding = "auto") { 
        if ($outputHeaders) { 
            if (is_string($outputHeaders)) { 
                $this->setFilename($outputHeaders); 
            } 
            $this->renderHeaders(); 
        } 
        rewind($this->buffer); 
        $output = stream_get_contents($this->buffer); 
        if ($to_encoding) { 
            $output = mb_convert_encoding($output, $to_encoding, $from_encoding); 
        } 
        return $this->output($output); 
    }
} 
