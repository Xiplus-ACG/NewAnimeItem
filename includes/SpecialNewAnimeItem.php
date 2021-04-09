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
class SpecialNewAnimeItem extends SpecialPage {

	public function __construct() {
		parent::__construct(
			'NewAnimeItem',
			'createpage'
		);

		$wikibaseRepo = WikibaseRepo::getDefaultInstance();
		$this->entityLookup = $wikibaseRepo->getEntityLookup( Store::LOOKUP_CACHING_RETRIEVE_ONLY );
		$this->basicEntityIdParser = new BasicEntityIdParser();
		$this->config = MediaWikiServices::getInstance()->getMainConfig();
	}

	/**
	 * @return bool
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * @param array $formData
	 *
	 * @return Item
	 */
	protected function createEntityFromFormData( array $formData ) {
		$languageCode = $this->config->get('NewAnimeItemDefaultLanguageCode');

		$item = [
			'labels' => [],
			'claims' => []
		];
		$item['labels'][$languageCode] = [
			'language' => $languageCode,
			'value' => $formData['animename']
		];

		$item['claims'][] = [
            'mainsnak' => [
                'snaktype' => 'value',
                'property' => $this->config->get('NewAnimeItemTypeId'),
                'datavalue' => [
                    'value' => [
                        'entity-type' => 'item',
                        // 'numeric-id' => 53,
                        'id' => $this->config->get('NewAnimeItemTypeAnimeId'),
                    ],
                    'type' => 'wikibase-entityid',
                ],
                'datatype' => 'wikibase-item',
            ],
            'type' => 'statement',
            'rank' => 'normal',
        ];

		$item['claims'][] = [
			'mainsnak' => [
                'snaktype' => 'value',
                'property' => $this->config->get('NewAnimeItemEpisodesSeenId'),
                'datavalue' => [
                    'value' => [
                        'amount' => '+' . $formData['seen'],
                        'unit' => '1',
                    ],
                    'type' => 'quantity',
                ],
                'datatype' => 'quantity',
            ],
            'type' => 'statement',
            'rank' => 'normal',
        ];

		$item['claims'][] = [
			'mainsnak' => [
                'snaktype' => 'value',
                'property' => $this->config->get('NewAnimeItemEpisodesAllId'),
                'datavalue' => [
                    'value' => [
                        'amount' => '+' . $formData['episodes'],
                        'unit' => '1',
                    ],
                    'type' => 'quantity',
                ],
                'datatype' => 'quantity',
            ],
            'type' => 'statement',
            'rank' => 'normal',
        ];

		$item['claims'][] = [
            'mainsnak' => [
                'snaktype' => 'value',
                'property' => $this->config->get('NewAnimeItemStatusId'),
                'datavalue' => [
                    'value' => [
                        'entity-type' => 'item',
                        // 'numeric-id' => 56,
                        'id' => $formData['status'],
                    ],
                    'type' => 'wikibase-entityid',
                ],
                'datatype' => 'wikibase-item',
            ],
            'type' => 'statement',
            'rank' => 'normal',
        ];

		$item['claims'][] = [
            'mainsnak' => [
                'snaktype' => 'value',
                'property' => $this->config->get('NewAnimeItemLengthId'),
                'datavalue' => [
                    'value' => [
                        'amount' => '+' . $formData['length'],
                        'unit' => $this->config->get('NewAnimeItemLengthMinute'),
                    ],
                    'type' => 'quantity',
                ],
                'datatype' => 'quantity',
            ],
            'type' => 'statement',
            'rank' => 'normal',
        ];

		if ($formData['zhwptitle']) {
			$item['claims'][] = [
				'mainsnak' => [
					'snaktype' => 'value',
					'property' => $this->config->get('NewAnimeItemZhwptitleId'),
					'datavalue' => [
						'value' => $formData['zhwptitle'],
						'type' => 'string',
					],
					'datatype' => 'external-id',
				],
				'type' => 'statement',
				'rank' => 'normal',
			];
		}

		if ($formData['gamerlink']) {
			$item['claims'][] = [
				'mainsnak' => [
					'snaktype' => 'value',
					'property' => $this->config->get('NewAnimeItemGamerlinkId'),
					'datavalue' => [
						'value' => $formData['gamerlink'],
						'type' => 'string',
					],
					'datatype' => 'url',
				],
				'type' => 'statement',
				'rank' => 'normal',
			];
		}

		if ($formData['videogamer']) {
			$item['claims'][] = [
				'mainsnak' => [
					'snaktype' => 'value',
					'property' => $this->config->get('NewAnimeItemVideoGamerId'),
					'datavalue' => [
						'value' => $formData['videogamer'],
						'type' => 'string',
					],
					'datatype' => 'url',
				],
				'type' => 'statement',
				'rank' => 'normal',
			];
		}

		if ($formData['videoanime1']) {
			$item['claims'][] = [
				'mainsnak' => [
					'snaktype' => 'value',
					'property' => $this->config->get('NewAnimeItemVideoAnime1Id'),
					'datavalue' => [
						'value' => $formData['videoanime1'],
						'type' => 'string',
					],
					'datatype' => 'url',
				],
				'type' => 'statement',
				'rank' => 'normal',
			];
		}

		if ($formData['videoage']) {
			$item['claims'][] = [
				'mainsnak' => [
					'snaktype' => 'value',
					'property' => $this->config->get('NewAnimeItemVideoAgeId'),
					'datavalue' => [
						'value' => $formData['videoage'],
						'type' => 'string',
					],
					'datatype' => 'url',
				],
				'type' => 'statement',
				'rank' => 'normal',
			];
		}

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
			->setWrapperLegendMsg( 'newanimeitem' )
			->suppressDefaultSubmit( true )
			->addHiddenField( 'wpEditToken', $this->getUser()->getEditToken() )
			->prepareForm()
			->displayForm( false );
	}

	protected function saveData() {
		$out = $this->getOutput();
		$config = MediaWikiServices::getInstance()->getMainConfig();

		$entity = $this->createEntityFromFormData( $this->getPostData() );

		$api = new ApiMain(
			new DerivativeRequest(
				$this->getRequest(), // Fallback upon $wgRequest if you can't access context
				array(
					'action'  => 'wbeditentity',
					'new'     =>'item',
					'data'    => json_encode($entity),
					'token'   => $this->getRequest()->getVal( 'wpEditToken' ),
					'summary' => wfMessage('newanimeitem-create-summary'),
				),
				true // treat this as a POST
			),
			true // Enable write.
		);
		$api->execute();

		$resultData = $api->getResult()->getResultData();

		$title = Title::newFromDBkey('Item:' . $resultData['entity']['id']); // TODO: Get namespace from config
		$entityUrl = $title->getFullURL();
		$this->getOutput()->redirect( $entityUrl );
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
			if ($data['animename'] !== '') {
				$data['zhwptitle'] = $this->getZhwptitleByName($data['animename']);
			}
		} else {
			$data['zhwptitle'] = $this->getZhwptitleByName($data['zhwptitle']); // Normalize
		}
		if ($data['gamerlink'] === '' && $data['animename'] !== '') {
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
			return str_replace(' ', '_', $page['title']);
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

	protected function getGroupName() {
		return 'wikibase';
	}
}
