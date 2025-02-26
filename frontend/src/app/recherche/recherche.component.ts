import { Component, Input, Output, EventEmitter, OnInit, ViewChild, ElementRef } from '@angular/core';
import { Router } from '@angular/router';
import { TokenService } from '../shared/token.service';
import { AuthStateService } from '../shared/auth-state.service';
import { AuthService } from '../shared/auth.service';
import { FetchService } from '../fetch.service';
import { ActivatedRoute } from '@angular/router';
import { ICellier } from '../icellier';
import { Imesbouteilles } from '../imesbouteilles';
import { environment } from '../../environments/environment';
import { ScannerComponent } from '../scanner/scanner.component';

@Component({
  selector: 'app-recherche',
  templateUrl: './recherche.component.html',
  styleUrls: ['./recherche.component.scss']
})

export class RechercheComponent {
  listeMesBouteilles: Array<Imesbouteilles>;
  unCellier: ICellier;
  @Input() isVisibleM = false;
  @Output() closed = new EventEmitter<void>();
  filteredData: any = [];
  searchTerm: any = '';
  id: number;
  isVisible = false;
  choixTypes: string[] = ['Vin rouge', 'Vin blanc', 'Vin rosé'];
  choixPays: string[] = ['Tout les pays', 'Afrique du Sud', 'Allemagne', 'Argentine', 'Arménie', 'Australie', 'Autriche', 'Bulgarie', 'Brésil', 'Canada', 'Chili', 'Chine', 'Croatie', 'Espagne', 'États-Unis', 'France', 'Géorgie', 'Grèce', 'Hongrie', 'Israël', 'Italie', 'Liban', 'Luxembourg', 'Maroc', 'Mexique', 'Moldavie', 'Nouvelle-Zélande', 'Portugal', 'République Tchèque', 'Roumanie', 'Slovénie', 'Suisse', 'Uruguay'];
  selectedWineTypes = new Set<string>();
  selectedWinePays = '';
  minPrice = '';
  maxPrice = '';
  nombreDeResultat: number;
  scannedBouteille: any;
  messageErreur: string = '';
  imgBouteilleNonDisponible = environment.baseImg + 'img/nonDispo.webp';
  iconeXb = environment.baseImg + 'icones/xb.png';

  /**
   * Fonction qui scanne le code bar d'une bouteille
   * @param scannedBouteille chaîne - Le code bar de la bouteille scannée
   */
  handleScan(scannedBouteille: string) {
    this.messageErreur="";
    this.scannedBouteille = scannedBouteille;
    const matchingBouteille = this.listeMesBouteilles.find(
      item => item.id === this.scannedBouteille.id
    );
    if (matchingBouteille) {
      this.router.navigate(['/profil/bouteille', matchingBouteille.id_supreme]);
    } else {
      // Show an error message to the user here
      this.messageErreur = "Il semble que cette bouteille ne soit pas stockée dans vos celliers. Veuillez vérifier à nouveau ou l'ajouter à vos celliers pour y accéder."
    }
  }

  /**
   * Fonction qui sélectionne le type de vin
   * @param type chaîne - Le type de vin
   * @returns Le type de vin sélectionné
   */
  isSelected(type: string): boolean {
    return this.selectedWineTypes.has(type);
  }

  /**
   * Fonction qui sélectionne le pays
   * @param pays chaîne - Le pays de production du vin
   * @returns Le pays de production du vin sélectionné
   */
  isSelectedPays(pays: string): boolean {
    return this.selectedWinePays === pays;
  }

  iconeTrash = '../assets/icones/trash-347.png';
  iconeModif = '../assets/icones/edit-black.png';

  constructor(
    private auth: AuthStateService,
    public router: Router,
    public token: TokenService,
    public authService: AuthService,
    public fetchService: FetchService,
    private route: ActivatedRoute,
  ) { }

  ngOnInit() {
    this.fetchService.getMesBouteilles().subscribe((data: any) => {
      this.listeMesBouteilles = data.data;
      for (let i = 0; i < this.listeMesBouteilles.length; i++) {
        if (this.listeMesBouteilles[i].nom == null) {
          this.listeMesBouteilles[i].nom = this.listeMesBouteilles[i].nom_bouteillePerso;
        }
        if (this.listeMesBouteilles[i].type_vino_name == null) {
          this.listeMesBouteilles[i].type_vino_name = this.listeMesBouteilles[i].type_mes_name;
        }
        if (this.listeMesBouteilles[i].pays == null) {
          this.listeMesBouteilles[i].pays = this.listeMesBouteilles[i].pays_bouteillePerso;
        }
        if (this.listeMesBouteilles[i].prix_saq == null) {
          this.listeMesBouteilles[i].prix_bouteillePerso = (this.listeMesBouteilles[i].prix_bouteillePerso).toFixed(2);
          this.listeMesBouteilles[i].prix = this.listeMesBouteilles[i].prix_bouteillePerso;
        } else {
          if (this.listeMesBouteilles[i].prix_bouteillePerso == null) {
            this.listeMesBouteilles[i].prix_saq = this.listeMesBouteilles[i].prix_saq.toFixed(2);
            this.listeMesBouteilles[i].prix = this.listeMesBouteilles[i].prix_saq;
          }
        }
      }
    });
  }

  /**
   * Fonction qui rétablie le tableau de bouteilles
   */
  resetData() {
    this.filteredData = [];
    this.searchTerm = "";
  }

  /**
   * Fonction qui filtre les bouteilles selon la valeur du champ
   * @param searchTerm chaîne - La valeur du champ recherche
   */
  filterData(searchTerm: string) {
    if (searchTerm.length < 1) {
      this.filteredData = [];
    } else {
      this.filteredData = this.listeMesBouteilles.filter(item =>
        item.nom.toLowerCase().includes(searchTerm.toLowerCase())
      );
      this.nombreDeResultat = null;
    }
  }

  /**
   * Fonction qui trie les bouteilles selon les types de vin
   * @param checked booléen - Vrai ou faux
   * @param type chaîne - Le type de vin
   */
  updateSelectedWineTypes(checked: boolean, type: string) {
    if (checked) {
      this.selectedWineTypes.add(type);
    } else {
      this.selectedWineTypes.delete(type);
    }
    this.filtreUltime();
  }

  /**
   * Fonction qui trie les bouteilles selon les pays de production de la bouteille de vin
   * @param value chaîne - Le pays
   */
  updateSelectedWinePays(value: string) {
    this.selectedWinePays = value;
    this.filtreUltime();
  }

  /**
   * Fonction qui filtre selon les prix minimum et maximum entrés par l'utilisateur
   * @param minPrice chaîne - Le prix minimum
   * @param maxPrice chaîne - Le prix maximum
   */
  filterByPrice(minPrice: string, maxPrice: string) {
    this.minPrice = minPrice;
    this.maxPrice = maxPrice;
    this.filtreUltime();
  }

  /**
   * Fonction qui filtre les bouteilles en tenant compte de tous les filtres et les tris
   * @returns Le tableau de bouteilles selon le filtre ultime
   */
  filtreUltime() {
    if ((this.minPrice && this.maxPrice) && this.selectedWinePays.length === 0 && this.selectedWineTypes.size === 0) {
      this.filteredData = this.listeMesBouteilles.filter((bouteille: any) => bouteille.prix >= this.minPrice && bouteille.prix <= this.maxPrice)
      return this.filteredData;
    }
    if (this.selectedWineTypes.size === 0 && this.selectedWinePays.length === 0) {
      this.filteredData = [];
      this.nombreDeResultat = null;
      return;
    }
    this.filteredData = this.listeMesBouteilles.filter((item: any) => {
      return (
        (this.selectedWineTypes.size === 0 || this.selectedWineTypes.has(item.type_vino_name)) &&
        (this.selectedWinePays === '' || this.selectedWinePays === 'Tout les pays' || item.pays === this.selectedWinePays) &&
        (this.minPrice === '' || item.prix >= parseInt(this.minPrice)) &&
        (this.maxPrice === '' || item.prix <= parseInt(this.maxPrice)) &&
        (item.nom.toLowerCase().includes(this.searchTerm.toLowerCase()))
      );
    });
    this.nombreDeResultat = this.filteredData.length;
  }
}
