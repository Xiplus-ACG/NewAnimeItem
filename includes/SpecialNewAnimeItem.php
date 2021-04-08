<?php

use SiteLookup;
use Status;
use DataValues\DecimalValue;
use DataValues\StringValue;
use DataValues\UnboundedQuantityValue;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Lib\Summary;
use Wikibase\Repo\CopyrightMessageBuilder;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikibase\Repo\Specials\SpecialNewEntity;
use Wikibase\Repo\Specials\SpecialPageCopyrightView;
use Wikibase\Repo\Store\Store;
use Wikibase\Repo\Store\TermsCollisionDetector;
use Wikibase\Repo\SummaryFormatter;
use Wikibase\Repo\Validators\TermValidatorFactory;
use Wikibase\Repo\WikibaseRepo;

/**
 * Page for creating new Wikibase items.
 *
 * @license GPL-2.0-or-later
 * @author John Erling Blad < jeblad@gmail.com >, Xiplus
 */
class SpecialNewAnimeItem extends SpecialNewEntity {

	public function __construct(
		SpecialPageCopyrightView $copyrightView,
		EntityNamespaceLookup $entityNamespaceLookup,
		SummaryFormatter $summaryFormatter,
		EntityTitleLookup $entityTitleLookup,
		MediawikiEditEntityFactory $editEntityFactory,
		SiteLookup $siteLookup,
		TermValidatorFactory $termValidatorFactory,
		TermsCollisionDetector $termsCollisionDetector
	) {
		parent::__construct(
			'NewAnimeItem',
			'createpage',
			$copyrightView,
			$entityNamespaceLookup,
			$summaryFormatter,
			$entityTitleLookup,
			$editEntityFactory
		);
		$this->siteLookup = $siteLookup;
		$this->termValidatorFactory = $termValidatorFactory;
		$this->termsCollisionDetector = $termsCollisionDetector;

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$this->entityLookup = $wikibaseRepo->getEntityLookup( Store::LOOKUP_CACHING_RETRIEVE_ONLY );
		$this->basicEntityIdParser = new BasicEntityIdParser();
		$this->config = MediaWikiServices::getInstance()->getMainConfig();
	}

	public static function newFromGlobalState(): self {
		$wikibaseRepo = WikibaseRepo::getDefaultInstance();

		$settings = $wikibaseRepo->getSettings();
		$copyrightView = new SpecialPageCopyrightView(
			new CopyrightMessageBuilder(),
			$settings->getSetting( 'dataRightsUrl' ),
			$settings->getSetting( 'dataRightsText' )
		);

		return new self(
			$copyrightView,
			$wikibaseRepo->getEntityNamespaceLookup(),
			$wikibaseRepo->getSummaryFormatter(),
			$wikibaseRepo->getEntityTitleLookup(),
			$wikibaseRepo->newEditEntityFactory(),
			$wikibaseRepo->getSiteLookup(),
			$wikibaseRepo->getTermValidatorFactory(),
			$wikibaseRepo->getItemTermsCollisionDetector()
		);
	}

	/**
	 * @see SpecialNewEntity::doesWrites
	 *
	 * @return bool
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * @see SpecialNewEntity::createEntityFromFormData
	 *
	 * @param array $formData
	 *
	 * @return Item
	 */
	protected function createEntityFromFormData( array $formData ) {
		$languageCode = $this->config->get('NewAnimeItemDefaultLanguageCode');

		$item = new Item();
		$item->setLabel( $languageCode, $formData['animename'] );
		$item->setDescription( $languageCode, '' );
		$item->setAliases( $languageCode, [] );

		$statementList = new StatementList();

		$statementList->addNewStatement(
			new PropertyValueSnak(
				new PropertyId($this->config->get('NewAnimeItemTypeId')),
				new EntityIdValue(new ItemId($this->config->get('NewAnimeItemTypeAnimeId')))
			)
		);

		$statementList->addNewStatement(
			new PropertyValueSnak(
				new PropertyId($this->config->get('NewAnimeItemEpisodesSeenId')),
				new UnboundedQuantityValue(new DecimalValue($formData['seen']), "1")
			)
		);

		$statementList->addNewStatement(
			new PropertyValueSnak(
				new PropertyId($this->config->get('NewAnimeItemEpisodesAllId')),
				new UnboundedQuantityValue(new DecimalValue($formData['episodes']), "1")
			)
		);

		$statementList->addNewStatement(
			new PropertyValueSnak(
				new PropertyId($this->config->get('NewAnimeItemStatusId')),
				new EntityIdValue(new ItemId($formData['status']))
			)
		);

		$statementList->addNewStatement(
			new PropertyValueSnak(
				new PropertyId($this->config->get('NewAnimeItemLengthId')),
				new UnboundedQuantityValue(new DecimalValue($formData['length']), $this->config->get('NewAnimeItemLengthMinute'))
			)
		);

		if ($formData['zhwptitle']) {
			$statementList->addNewStatement(
				new PropertyValueSnak(
					new PropertyId($this->config->get('NewAnimeItemZhwptitleId')),
					new StringValue($formData['zhwptitle'])
				)
			);
		}

		if ($formData['gamerlink']) {
			$statementList->addNewStatement(
				new PropertyValueSnak(
					new PropertyId($this->config->get('NewAnimeItemGamerlinkId')),
					new StringValue($formData['gamerlink'])
				)
			);
		}

		if ($formData['videogamer']) {
			$statementList->addNewStatement(
				new PropertyValueSnak(
					new PropertyId($this->config->get('NewAnimeItemVideoGamerId')),
					new StringValue($formData['videogamer'])
				)
			);
		}

		if ($formData['videoanime1']) {
			$statementList->addNewStatement(
				new PropertyValueSnak(
					new PropertyId($this->config->get('NewAnimeItemVideoAnime1Id')),
					new StringValue($formData['videoanime1'])
				)
			);
		}

		if ($formData['videoage']) {
			$statementList->addNewStatement(
				new PropertyValueSnak(
					new PropertyId($this->config->get('NewAnimeItemVideoAgeId')),
					new StringValue($formData['videoage'])
				)
			);
		}

		$item->setStatements($statementList);

		return $item;
	}

	/**
	 * @see SpecialWikibasePage::execute
	 *
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkReadOnly();
		$this->outputHeader();

		$this->parts = $subPage;

		$currentUser = $this->getUser();
		$req = $this->getRequest();
		$submit = $req->getRawVal('submit');

		if ($req->wasPosted() && $submit === 'save'
			&& $currentUser->matchEditToken( $req->getVal( 'wpEditToken' ))
		) {
			$this->saveData();
		} else {
			$this->createForm();
		}
	}

	/**
	 * @return array[]
	 */
	protected function getFormFields() {
		$data = $this->getPostData();

		$formFields = [
			'animename' => [
				'id' => 'newanimeitem-animename',
				'default' => $data['animename'],
				'label-message' => 'wikibase-newentity-label',
				'type' => 'text',
				'name' => 'animename'
			],
			'seen' => [
				'id' => 'newanimeitem-seen',
				'default' => 1,
				'label' => $this->getLabelById($this->config->get('NewAnimeItemEpisodesSeenId')),
				'type' => 'int',
				'name' => 'seen'
			],
			'episodes' => [
				'id' => 'newanimeitem-episodes',
				'default' => 1,
				'label' => $this->getLabelById($this->config->get('NewAnimeItemEpisodesAllId')),
				'type' => 'int',
				'name' => 'episodes'
			],
			'status' => [
				'id' => 'newanimeitem-status',
				'label' => $this->getLabelById($this->config->get('NewAnimeItemStatusId')),
				'type' => 'select',
				'options' => [
					$this->getLabelById($this->config->get('NewAnimeItemStatusNotStartId')) => $this->config->get('NewAnimeItemStatusNotStartId'),
					$this->getLabelById($this->config->get('NewAnimeItemStatusPlayingId')) => $this->config->get('NewAnimeItemStatusPlayingId'),
					$this->getLabelById($this->config->get('NewAnimeItemStatusEndId')) => $this->config->get('NewAnimeItemStatusEndId'),
				],
				'default' => $this->config->get('NewAnimeItemStatusPlayingId'),
				'name' => 'status'
			],
			'length' => [
				'id' => 'newanimeitem-length',
				'default' => 24,
				'label' => $this->getLabelById($this->config->get('NewAnimeItemLengthId')),
				'type' => 'int',
				'name' => 'length'
			],
			'zhwptitle' => [
				'id' => 'newanimeitem-zhwptitle',
				'default' => $data['zhwptitle'],
				'label' => $this->getLabelById($this->config->get('NewAnimeItemZhwptitleId')),
				'type' => 'text',
				'name' => 'zhwptitle'
			],
			'gamerlink' => [
				'id' => 'newanimeitem-gamerlink',
				'default' => $data['gamerlink'],
				'label' => $this->getLabelById($this->config->get('NewAnimeItemGamerlinkId')),
				'type' => 'url',
				'name' => 'gamerlink'
			],
			'videogamer' => [
				'id' => 'newanimeitem-videogamer',
				'default' => $data['videogamer'],
				'label' => $this->getLabelById($this->config->get('NewAnimeItemVideoGamerId')),
				'type' => 'url',
				'name' => 'videogamer'
			],
			'videoanime1' => [
				'id' => 'newanimeitem-videoanime1',
				'default' => $data['videoanime1'],
				'label' => $this->getLabelById($this->config->get('NewAnimeItemVideoAnime1Id')),
				'type' => 'url',
				'name' => 'videoanime1'
			],
			'videoage' => [
				'id' => 'newanimeitem-videoage',
				'default' => $data['videoage'],
				'label' => $this->getLabelById($this->config->get('NewAnimeItemVideoAgeId')),
				'type' => 'url',
				'name' => 'videoage'
			],
			'preview' => [
				'buttonlabel-message' => 'showpreview',
				'type' => 'submit',
				'name' => 'submit',
				'default' => 'preview'
			],
			'submit' => [
				'buttonlabel-message' => 'wikibase-newentity-submit',
				'type' => 'submit',
				'name' => 'submit',
				'default' => 'save'
			]
		];

		return $formFields;
	}

	/**
	 * @see SpecialNewEntity::getLegend
	 *
	 * @return string|Message $msg Message key or Message object
	 */
	protected function getLegend() {
		return $this->msg( 'wikibase-newitem-fieldset' );
	}

	/**
	 * @see SpecialNewEntity::getWarnings
	 *
	 * @return string[]
	 */
	protected function getWarnings() {
		return [];
	}

	private function createForm() {
		$out = $this->getOutput();

		$data = $this->getPostData();

		$previewText = "預覽結果：\n";
		$previewText .= "* 動畫名稱：" . $data['animename'] . "\n";
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemEpisodesSeenId')) . "：" . $data['seen'] . "\n";
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemEpisodesAllId')) . "：" . $data['episodes'] . "\n";
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemStatusId')) . "：" . $this->getLabelById($data['status']) . "\n";
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemLengthId')) . "：" . $data['length']  . "\n";
		if ($data['zhwptitle']) {
			$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemZhwptitleId')) . "：https://zh.wikipedia.org/wiki/" . $data['zhwptitle'] . "\n";
		}
		if ($data['gamerlink']) {
			$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemGamerlinkId')) . "：" . $data['gamerlink'] . "\n";
		}
		if ($data['year']) {
			$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemYearId')) . "：" . $data['year'] . "\n";
		}
		if ($data['videogamer']) {
			$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemVideoGamerId')) . "：" . $data['videogamer'] . "\n";
		}
		if ($data['videoanime1']) {
			$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemVideoAnime1Id')) . "：" . $data['videoanime1'] . "\n";
		}
		if ($data['videoage']) {
			$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemVideoAgeId')) . "：" . $data['videoage'] . "\n";
		}
		$out->addWikiTextAsInterface($previewText);

		HTMLForm::factory( 'ooui', $this->getFormFields(), $this->getContext() )
			->setName( 'newanimeitem' )
			->setFormIdentifier( 'newanimeitem' )
			->setWrapperLegendMsg( 'special-newanimeitem' )
			->suppressDefaultSubmit( true )
			->addHiddenField( 'wpEditToken', $this->getUser()->getEditToken() )
			->prepareForm()
			->displayForm( false );
	}

	protected function saveData() {
		$out = $this->getOutput();
		$config = MediaWikiServices::getInstance()->getMainConfig();

		$entity = $this->createEntityFromFormData( $this->getPostData() );

		$summary = $this->createSummary( $entity );

		$this->prepareEditEntity();
		$saveStatus = $this->saveEntity(
			$entity,
			$summary,
			$this->getRequest()->getRawVal( 'wpEditToken' ),
			EDIT_NEW
		);

		if ( $saveStatus && $saveStatus->isGood() ) {
			$title = $this->getEntityTitle( $entity->getId() );
			$entityUrl = $title->getFullURL();
			$this->getOutput()->redirect( $entityUrl );
			return;
		}

		$this->createForm();
	}

	/**
	 * @param array $formData
	 *
	 * @return Status
	 */
	protected function validateFormData( array $formData ) {
		return Status::newGood();
	}

	protected function createSummary( EntityDocument $item ) {
		$uiLanguageCode = $this->getLanguage()->getCode();

		$summary = new Summary( 'wbeditentity', 'create' );
		$summary->setLanguage( $uiLanguageCode );
		/** @var Term|null $labelTerm */
		$labelTerm = $item->getLabels()->getIterator()->current();
		/** @var Term|null $descriptionTerm */
		$descriptionTerm = $item->getDescriptions()->getIterator()->current();
		$summary->addAutoSummaryArgs(
			$labelTerm ? $labelTerm->getText() : '',
			$descriptionTerm ? $descriptionTerm->getText() : ''
		);

		return $summary;
	}

	private function getPostData() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$req = $this->getRequest();

		$data = [];

		$data['animename'] = trim( $req->getText( 'animename', $this->parts ) );
		$data['seen'] = $req->getInt( 'seen', 1 );
		$data['episodes'] = $req->getInt( 'episodes', 1 );
		$data['status'] = trim( $req->getText( 'status' ) ) ?: $this->config->get('NewAnimeItemStatusPlayingId');
		$data['length'] = $req->getInt( 'length', 24 );
		$data['zhwptitle'] = trim( $req->getText( 'zhwptitle' ) );
		$data['gamerlink'] = trim( $req->getText( 'gamerlink' ) );
		$data['videogamer'] = trim( $req->getText( 'videogamer' ) );
		$data['videoanime1'] = trim( $req->getText( 'videoanime1' ) );
		$data['videoage'] = trim( $req->getText( 'videoage' ) );

		if ($data['zhwptitle'] === '') {
			$data['zhwptitle'] = $this->getZhwptitleByName($data['animename']);
		} else {
			$data['zhwptitle'] = $this->getZhwptitleByName($data['zhwptitle']); // Normalize
		}
		if ($data['gamerlink'] === '') {
			$data['gamerlink'] = $this->getGamerlinkByName($data['animename']);
		}
		if ($data['videogamer'] === '' && $data['gamerlink'] !== '') {
			$temp = $this->getGamerInfo($data['gamerlink']);
			$data['videogamer'] = $temp['video'];
		}

		return $data;
	}

	private function getZhwptitleByName($animename) {
		$http = MediaWikiServices::getInstance()->getHttpRequestFactory();

		$zhwpres = $http->post(
			'https://zh.wikipedia.org/w/api.php',
			[
				'postData' => [
					'action' => 'query',
					'format' => 'json',
					'titles' => $animename,
					'redirects' => 1,
					'converttitles' => 1,
					'utf8' => 1,
					'formatversion' => 2
				]
			]
		);
		$zhwpres = json_decode($zhwpres, true);

		if (count($zhwpres['query']['pages']) > 0) {
			$page = $zhwpres['query']['pages'][0];
			if (isset($page['missing'])) {
				return null;
			}
			return $page['title'];
		}
	}

	private function getGamerlinkByName($animename) {
		$gamerurl = 'https://acg.gamer.com.tw/search.php?' . http_build_query([
			'kw' => $animename,
			's' => 1
		]);
		// $gamerres = $http->get($gamerurl);
		$gamerres = file_get_contents($gamerurl);

		if (preg_match('/\[ 動畫 \]\n<a target="_blank" href="([^"]+?)"/', $gamerres, $m)) {
			return 'https:' . $m[1];
		}

		return null;
	}

	private function getGamerInfo($url) {
		$res = file_get_contents($url);

		$data = [];

		if (preg_match('/當地(?:首播|發售)：(\d{4})-(\d{2})-(\d{2})/', $res, $m)) {
			$data['year'] = $m[1] . $m[2] . $m[3];
		}
		if (preg_match('/<div class="seasonACG"><ul><li><a href="([^"]*?)"/', $res, $m)) {
			$data['video'] = 'https:' . $m[1];
		}

		return $data;
	}

	private function getLabelById(string $id) {
		if ($id === '') {
			return '';
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$languageCode = $this->config->get('NewAnimeItemDefaultLanguageCode');

		$entityId = $this->basicEntityIdParser->parse($id);
		$entity = $this->entityLookup->getEntity($entityId);
		$labels = $entity->getLabels();

		return $labels->toTextArray()[$languageCode];
	}

	protected function getEntityType() {
		return Item::ENTITY_TYPE;
	}
}
