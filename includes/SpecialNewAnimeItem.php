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

		if ($formData['rundate']) {
			$item['claims'][] = [
				'mainsnak' => [
					'snaktype' => 'value',
					'property' => $this->config->get('NewAnimeItemRundateId'),
					'datavalue' => [
						'value' => [
							'time' => '+' . $formData['rundate'] . 'T00:00:00Z',
							'timezone' => 0,
							'before' => 0,
							'after' => 0,
							'precision' => 11,
							'calendarmodel' => $this->config->get('NewAnimeItemCalendarModel')
						],
						'type' => 'time',
					],
					'datatype' => 'time',
				],
				'type' => 'statement',
				'rank' => 'normal',
			];
		}

		if ($formData['rating']) {
			$claim = [
				'mainsnak' => [
					'snaktype' => 'value',
					'property' => $this->config->get('NewAnimeItemRatingId'),
					'datavalue' => [
						'value' => [
							'entity-type' => 'item',
							'id' => $formData['rating'],
						],
						'type' => 'wikibase-entityid',
					],
					'datatype' => 'wikibase-item',
				],
				'type' => 'statement',
				'rank' => 'normal',
				'references' => []
			];
			if (isset($formData['rating_ref_key']) && isset($formData['rating_ref_value'])) {
				$claim['references'][] = [
					'snaks' => [
						[
							'snaktype' => 'value',
							'property' => $formData['rating_ref_key'],
							'datavalue' => [
								'value' => $formData['rating_ref_value'],
								'type' => 'string'
							],
							'datatype' => 'url'
						]
					]
				];

			};
			$item['claims'][] = $claim;
		}

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
		$this->checkPermissions();
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
				'type' => 'float',
				'name' => 'length'
			],
			'rundate' => [
				'id' => 'newanimeitem-rundate',
				'label' => $this->getLabelById($this->config->get('NewAnimeItemRundateId')),
				'type' => 'text',
				'name' => 'rundate'
			],
			'rating' => [
				'id' => 'newanimeitem-rating',
				'label' => $this->getLabelById($this->config->get('NewAnimeItemRatingId')),
				'type' => 'select',
				'options' => [
					'無' => '',
					$this->getLabelById($this->config->get('NewAnimeItemRating0Id')) => $this->config->get('NewAnimeItemRating0Id'),
					$this->getLabelById($this->config->get('NewAnimeItemRating6Id')) => $this->config->get('NewAnimeItemRating6Id'),
					$this->getLabelById($this->config->get('NewAnimeItemRating12Id')) => $this->config->get('NewAnimeItemRating12Id'),
					$this->getLabelById($this->config->get('NewAnimeItemRating15Id')) => $this->config->get('NewAnimeItemRating15Id'),
					$this->getLabelById($this->config->get('NewAnimeItemRating18Id')) => $this->config->get('NewAnimeItemRating18Id'),
				],
				'default' => '',
				'name' => 'rating'
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
				'type' => 'text',
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
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemRundateId')) . "：" . ($data['rundate'] ?: "無")  . "\n";
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemRatingId')) . "：" . ($data['rating'] ? $this->getLabelById($data['rating']) : "無")  . "\n";
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemZhwptitleId')) . "：" . ($data['zhwptitle'] ? "https://zh.wikipedia.org/wiki/" . $data['zhwptitle'] : "無") . "\n";
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemGamerlinkId')) . "：" . ($data['gamerlink'] ?: "無") . "\n";
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemVideoGamerId')) . "：" . ($data['videogamer'] ?: "無") . "\n";
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemVideoAnime1Id')) . "：" . ($data['videoanime1'] ?: "無") . "\n";
		$previewText .= "* " . $this->getLabelById($this->config->get('NewAnimeItemVideoAgeId')) . "：" . ($data['videoage'] ?: "無") . "\n";
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
		$data['length'] = $req->getFloat( 'length', 24 );
		$data['rundate'] = $req->getText( 'rundate', '' );
		$data['rating'] = $req->getText( 'rating', '' );
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
		if ($data['gamerlink'] !== '') {
			$gamerInfo = $this->getGamerInfo($data['gamerlink']);

			if ($data['rundate'] === '' && isset($gamerInfo['rundate'])) {
				$data['rundate'] = $gamerInfo['rundate'];
			}

			if ($data['rating'] === '' && isset($gamerInfo['rating'])) {
				$RATING_ITEM = [
					0 => $this->config->get('NewAnimeItemRating0Id'),
					6 => $this->config->get('NewAnimeItemRating6Id'),
					12 => $this->config->get('NewAnimeItemRating12Id'),
					15 => $this->config->get('NewAnimeItemRating15Id'),
					18 => $this->config->get('NewAnimeItemRating18Id'),
				];
				$data['rating'] = $RATING_ITEM[$gamerInfo['rating']];
				$data['rating_ref_key'] = $this->config->get('NewAnimeItemGamerlinkId');
				$data['rating_ref_value'] = $data['gamerlink'];
			}

			if ($data['videogamer'] === '' && isset($gamerInfo['video'])) {
				$data['videogamer'] = $gamerInfo['video'];
			}
		}

		foreach ($data as $key => $value) {
			if ($value === '-') {
				$data[$key] = '';
			}
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
		$gamerres = file_get_contents($gamerurl);

		if (preg_match('/\[ 動畫 \]\n<a target="_blank" href="([^"]+?)"/', $gamerres, $m)) {
			return 'https:' . $m[1];
		}

		return null;
	}

	private function getGamerInfo($url) {
		$res = file_get_contents($url);

		$data = [];

		if (preg_match('/當地(?:首播|發售)：(\d{4}-\d{2}-\d{2})/', $res, $m)) {
			$data['rundate'] = $m[1];
		}
		if (preg_match('/<div class="seasonACG"><ul><li><a href="([^"]*?)"/', $res, $m)) {
			$data['video'] = 'https:' . $m[1];
		}

		$RATING_IMG = [
			'ALL' => 0,
			'6TO12' => 6,
			'12TO18' => 12,
			'15TO18' => 15,
			'18UP' => 18,
		];
		if (preg_match('/<img src="https:\/\/i2.bahamut.com.tw\/acg\/TW-(.+?).gif"/', $res, $m)) {
			if (isset($RATING_IMG[$m[1]])) {
				$data['rating'] = $RATING_IMG[$m[1]];
			}
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
