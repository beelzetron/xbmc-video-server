<?php

/**
 * Form model for the movie filter
 *
 * @author Sam Stenvall <neggelandia@gmail.com>
 * @copyright Copyright &copy; Sam Stenvall 2013-
 * @license https://www.gnu.org/licenses/gpl.html The GNU General Public License v3.0
 */
class MovieFilterForm extends VideoFilterForm
{

	const QUALITY_SD = 'sd';
	const QUALITY_720 = 720;
	const QUALITY_1080 = 1080;

	/**
	 * @var int the movie year
	 */
	public $year;
	
	/**
	 * @var string the video quality
	 */
	public $quality;

	/**
	 * Populates and returns the list of genres
	 * @implements VideoFilterForm
	 * @return array
	 */
	public function getGenres()
	{
		if (empty($this->_genres))
		{
			foreach (VideoLibrary::getGenres() as $genre)
				$this->_genres[$genre->label] = $genre->label;
		}

		return $this->_genres;
	}
	
	/**
	 * @return array the attribute labels for this model
	 */
	public function attributeLabels()
	{
		return array_merge(parent::attributeLabels(), array(
			'year'=>'Year',
			'quality'=>'Quality',
		));
	}

	/**
	 * @return array the validation rules for this model
	 */
	public function rules()
	{
		return array_merge(parent::rules(), array(
			array('year', 'numerical', 'integerOnly'=>true),
			array('quality', 'in', 'range'=>array_keys($this->getQualities())),
		));
	}

	/**
	 * Returns the possible qualities
	 * @return array
	 */
	public function getQualities()
	{
		return array(
			self::QUALITY_SD=>'SD',
			self::QUALITY_720=>'720p',
			self::QUALITY_1080=>'1080p',
		);
	}
	
	/**
	 * Returns the defined filter as an array
	 * @return array the filter
	 */
	public function getFilterDefinitions()
	{
		$filter = array();

		// Include partial matches on movie title
		$filter['title'] = array(
			'operator'=>'contains',
			'value'=>$this->name);

		$filter['genre'] = array(
			'operator'=>'is',
			'value'=>$this->genre);

		$filter['year'] = array(
			'operator'=>'is',
			'value'=>$this->year);

		$quality = $this->quality;

		// SD means anything less than 720p
		if ($quality == self::QUALITY_SD)
		{
			$filter['videoresolution'] = array(
				'operator'=>'lessthan',
				'value'=>(string)self::QUALITY_720);
		}
		else
		{
			$filter['videoresolution'] = array(
				'operator'=>'is',
				'value'=>$quality);
		}

		return $filter;
	}

}