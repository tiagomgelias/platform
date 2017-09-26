<?phpnamespace Selenia\Platform\Models;use Electro\Exceptions\FlashMessageException;use Electro\Exceptions\FlashType;use Exception;class TranslationData{  const KEY_WIDTH = 24; //max.padding for saved keys  private $pathIniFileToWrite;  private $iniFileToWrite;  function save (array $data, $path)  {    if (fileExists($path))      $iniFile    = parse_ini_file ($path);    else      $iniFile = [];    $this->iniFileToWrite = array_merge ($iniFile, $data);    $this->pathIniFileToWrite = $path;    $this->write();  }  private function write()  {    $f = @fopen ($this->pathIniFileToWrite, 'w');    if ($f === false)      throw new FlashMessageException('Não foi possível abrir o seguinte ficheiro: ' . $this->pathIniFileToWrite, FlashType::ERROR);    try    {      foreach ($this->iniFileToWrite as $key => $value)      {        $v = str_replace ('"', '\\"', $value);        $v = str_replace ('&', '&amp;', $v);        $k = str_pad ($key, self::KEY_WIDTH);        fwrite ($f, "$k = \"$v\"\n");      }    }    catch (Exception $e) {      fclose ($f);      throw $e;    }    fclose ($f);  }  function delete($sKey,$path,$optDeleteFile = false)  {    if (!fileExists($path))      return;    $iniFile = parse_ini_file ($path);    if (isset($iniFile[$sKey]))      unset($iniFile[$sKey]);    $this->pathIniFileToWrite = $path;    $this->iniFileToWrite = $iniFile;    $this->write();    if ($optDeleteFile && count($this->iniFileToWrite)==0 && fileExists($this->pathIniFileToWrite))      unlink($this->pathIniFileToWrite);  }  function get($sKey,$path)  {    if (fileExists($path))    {      $data = parse_ini_file($path);      return get($data,$sKey);    }  }}