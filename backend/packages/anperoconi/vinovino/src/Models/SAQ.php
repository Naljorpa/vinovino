<?php
namespace Anperoconi\Vinovino\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DOMDocument;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Class MonSQL
 * Classe qui génère ma connection à MySQL à travers un singleton
 *
 *
 * @author Jonathan Martel
 * @version 1.0
 *
 *
 *
 */
class SAQ extends Model
{

	const DUPLICATION = 'duplication';
	const ERREURDB = 'erreurdb';
	const INSERE = 'Nouvelle bouteille insérée';

	private static $_webpage;
	private static $_status;
	private $stmt;

//	public function __construct()
//	{
//		parent::__construct();
//		if (!($this->stmt = $this->_db->prepare("INSERT INTO vino__bouteille(nom, type, image, code_saq, pays, description, prix_saq, url_saq, url_img, format) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"))) {
//			echo "Echec de la préparation : (" . $this->_db->errno . ") " . $this->_db->error;
//		}
//	}
	function getMaxPages()
	{

		$nombre = 96;
		$maxPages = 1;

		ini_set('max_execution_time', 0);
		$s = curl_init();
		$url = "https://www.saq.com/fr/produits/vin?p=1&product_list_limit=" . $nombre . "&product_list_order=name_asc";

		curl_setopt_array($s, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:60.0) Gecko/20100101 Firefox/60.0',
			CURLOPT_ENCODING => 'gzip, deflate',
			CURLOPT_HTTPHEADER => array(
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-US,en;q=0.5',
				'Accept-Encoding: gzip, deflate',
				'Connection: keep-alive',
				'Upgrade-Insecure-Requests: 1',
			),
		));

		$webpage = curl_exec($s);
		$status = curl_getinfo($s, CURLINFO_HTTP_CODE);
		curl_close($s);

		$doc = new DOMDocument();
		@$doc->loadHTML($webpage);

		$elements = $doc->getElementsByTagName("p");
		foreach ($elements as $key => $noeud) {
			if ($noeud->getAttribute('class') == "toolbar-amount") {
				$text = $noeud->textContent;
				preg_match("/sur\s*(\d+)/", $text, $matches);
				$total = intval($matches[1]);
				$maxPages = ceil($total / $nombre);
				break;
			}
		}
		return $maxPages;
	}



	/**
	 * getProduits
	 * @param int $nombre
	 * @param int $debut
	 */
	public function getProduits($nombre = 96, $page, $numero_de_page, $temps_debut)
	{
		ini_set('max_execution_time', 0);
		$s = curl_init();
		$url = "https://www.saq.com/fr/produits/vin?p=" . $page . "&product_list_limit=" . $nombre . "&product_list_order=name_asc";
		// Se prendre pour un navigateur pour berner le serveur de la saq...
		curl_setopt_array($s, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:60.0) Gecko/20100101 Firefox/60.0',
			CURLOPT_ENCODING => 'gzip, deflate',
			CURLOPT_HTTPHEADER => array(
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-US,en;q=0.5',
				'Accept-Encoding: gzip, deflate',
				'Connection: keep-alive',
				'Upgrade-Insecure-Requests: 1',
			),
		));
		self::$_webpage = curl_exec($s);
		self::$_status = curl_getinfo($s, CURLINFO_HTTP_CODE);
		curl_close($s);

		$doc = new DOMDocument();
		$doc->recover = true;
		$doc->strictErrorChecking = false;
		@$doc->loadHTML(self::$_webpage);
		$elements = $doc->getElementsByTagName("li");
		$i = 0;
		foreach ($elements as $key => $noeud) {
			if (strpos($noeud->getAttribute('class'), "product-item") !== false) {
				$info = self::recupereInfo($noeud);
				$retour = $this->ajouteProduit($info);
				if ($retour->succes == false) {
				} else {
					$i++;
				}
			}
		}

        // Mettre à jour la table progres__crawler pour indiquer le nombre de pages complétées
        // à chaque page insérée dans la base de données
        // Exemple de parametres de getProduits
        //public function getProduits($nombre = 96, $page, $numero_de_page, $temps_debut)
        $resultat = DB::table('progres__crawler')
            ->where('temps_debut', $temps_debut)
            ->update(['nb_pages_completees' => $numero_de_page]);
		return $i;
	}
	private function get_inner_html($node)
	{
		$innerHTML = '';
		$children = $node->childNodes;
		foreach ($children as $child) {
			$innerHTML .= $child->ownerDocument->saveXML($child);
		}

		return $innerHTML;
	}

	private function nettoyerEspace($chaine)
	{
		return preg_replace('/\s+/', ' ', $chaine);
	}

	private function recupereInfo($noeud)
	{

		$info = new stdClass();
		// $info->img = $noeud->getElementsByTagName("img")->item(0)->getAttribute('src'); //TODO : Nettoyer le lien

		$imgElements = $noeud->getElementsByTagName('img');
		foreach ($imgElements as $imgElement) {
			if ($imgElement->hasAttribute('class') && $imgElement->getAttribute('class') === 'product-image-photo') {
				$info->img = $imgElement->getAttribute('src');
			}
		}

		$a_titre = $noeud->getElementsByTagName("a")->item(0);
		$info->url = $a_titre->getAttribute('href');

		//var_dump($noeud -> getElementsByTagName("a")->item(1)->textContent);
		$nom = $noeud->getElementsByTagName("a")->item(1)->textContent;
		//var_dump($a_titre);
		$info->nom = self::nettoyerEspace(trim($nom));
		//var_dump($info -> nom);
		// Type, format et pays
		$aElements = $noeud->getElementsByTagName("strong");
		foreach ($aElements as $node) {
			if ($node->getAttribute('class') == 'product product-item-identity-format') {
				$info->desc = new stdClass();
				$info->desc->texte = $node->textContent;
				$info->desc->texte = self::nettoyerEspace($info->desc->texte);
				$aDesc = explode("|", $info->desc->texte); // Type, Format, Pays
				if (count($aDesc) == 3) {

					$info->desc->type = trim($aDesc[0]);
					$info->desc->format = trim($aDesc[1]);
					$info->desc->pays = trim($aDesc[2]);
				}

				$info->desc->texte = trim($info->desc->texte);
				// $info->type = $info->desc->type = trim($aDesc[0]);
			}
		}

		//Code SAQ
		$aElements = $noeud->getElementsByTagName("div");
		foreach ($aElements as $node) {
			if ($node->getAttribute('class') == 'saq-code') {
				if (preg_match("/\d+/", $node->textContent, $aRes)) {
					$info->desc->code_SAQ = trim($aRes[0]);
				}
			}
		}

		$aElements = $noeud->getElementsByTagName("span");
		foreach ($aElements as $node) {
			if ($node->getAttribute('class') == 'price') {
				$info->prix = trim($node->textContent);
			}
		}
		//var_dump($info);
		return $info;
	}

	private function ajouteProduit($bte)
	{
		$retour = new stdClass();
		$retour->succes = false;
		$retour->raison = '';
        $rows = DB::select( "SELECT id FROM vino__type WHERE type = :type", array(
            'type' => $bte->desc->type,
        ));
        if (count($rows) == 1) {
			$type = $rows[0]->id;
            $code_saq = (string) $bte->desc->code_SAQ; // cast to string
            $bte->desc->code_SAQ = $code_saq;
            $rows = DB::select( DB::raw("SELECT id FROM vino__bouteille WHERE code_saq = :code_saq"), array(
                'code_saq' => $bte->desc->code_SAQ,
            ));
            if (count($rows) < 1) {
				$prixF = str_replace("$", "", $bte->prix);
				$prixF = explode(",", $prixF);
				$prixF = $prixF[0] . "." . $prixF[1];
				$prixF = floatval($prixF);
				$prixF = number_format($prixF, 2, '.', ''); // format the float value with 2 decimal places
				$prixF = (float)$prixF; // convert the formatted string back to float
                $response = DB::insert( DB::raw("INSERT INTO vino__bouteille(nom, type, image, code_saq, pays,
                            description, prix_saq, url_saq, url_img, format) VALUES (:nom, :type, :img, :code_SAQ, :pays, :description,
                                                                                     :prix_saq, :url_saq, :url_img, :format)"), array(
                    'nom' => $bte->nom,'type' => $type,'img' => $bte->img,'code_SAQ' => $bte->desc->code_SAQ,
                    'pays'=>$bte->desc->pays,'description'=> $bte->desc->texte,'prix_saq' => $prixF,'url_saq' =>
                        $bte->url,'url_img' => $bte->img,'format' => $bte->desc->format
                ));

                dispatch(new \App\Jobs\Crawler($code_saq));
				$retour->raison = self::INSERE;
			} else {
				$retour->succes = false;
				$retour->raison = self::DUPLICATION;
			}
		} else {
			$retour->succes = false;
			$retour->raison = self::ERREURDB;
		}
		return $retour;
	}
}
