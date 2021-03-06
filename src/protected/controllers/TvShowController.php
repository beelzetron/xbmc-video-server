<?php

/**
 * Handles TV shows
 *
 * @author Sam Stenvall <neggelandia@gmail.com>
 * @copyright Copyright &copy; Sam Stenvall 2013-
 * @license https://www.gnu.org/licenses/gpl.html The GNU General Public License v3.0
 */
class TvShowController extends MediaController
{
	
	protected function getSpectatorProhibitedActions()
	{
		return array('getEpisodePlaylist', 'getSeasonPlaylist');
	}

	/**
	 * Lists all TV shows in the library
	 */
	public function actionIndex()
	{
		// Get the appropriate request parameters from the filter
		$filterForm = new TVShowFilterForm();

		$requestParameters = array(
			'properties'=>array('thumbnail', 'fanart', 'art'));

		if (isset($_GET['TVShowFilterForm']))
		{
			$filterForm->attributes = $_GET['TVShowFilterForm'];

			if (!$filterForm->isEmpty() && $filterForm->validate())
				$requestParameters['filter'] = $filterForm->getFilter();
		}

		$tvshows = VideoLibrary::getTVShows($requestParameters);
		
		// Go directly to the details page if we have an exact match on the 
		// show name
		if (count($tvshows) === 1 && $filterForm->name === $tvshows[0]->label)
			$this->redirect(array('details', 'id'=>$tvshows[0]->tvshowid));

		$this->render('index', array(
			'dataProvider'=>new LibraryDataProvider($tvshows, 'tvshowid'),
			'filterForm'=>$filterForm));
	}
	
	/**
	 * Displays information about the specified show
	 * @param int $id the show ID
	 * @throws CHttpException if the show could not be found
	 */
	public function actionDetails($id)
	{
		$showDetails = VideoLibrary::getTVShowDetails($id, array(
			'title',
			'genre',
			'year',
			'rating',
			'plot',
			'mpaa',
			'imdbnumber',
			'thumbnail',
			'cast',
		));

		if ($showDetails === null)
			throw new CHttpException(404, 'Not found');
		
		$actorDataProvider = new CArrayDataProvider(
				$showDetails->cast, array(
			'keyField'=>'name',
			'pagination'=>array('pageSize'=>6)
		));
		
		// Check backend version and warn about incompatibilities
		if (!Yii::app()->xbmc->meetsMinimumRequirements() && !Setting::getValue('disableFrodoWarning'))
			Yii::app()->user->setFlash('info', 'Streaming of video files is not possible from XBMC 12 "Frodo" and earlier backends');
		
		$this->render('details', array(
			'details'=>$showDetails,
			'seasons'=>VideoLibrary::getSeasons($id),
			'actorDataProvider'=>$actorDataProvider));
	}
	
	/**
	 * Serves a playlist containing all episodes from the specified TV show 
	 * season.
	 * @param int $tvshowId the TV show ID
	 * @param int $season the season
	 */
	public function actionGetSeasonPlaylist($tvshowId, $season)
	{
		$tvshowDetails = VideoLibrary::getTVShowDetails($tvshowId, array());
		$episodes = VideoLibrary::getEpisodes($tvshowId, $season, array(
					'episode',
					'runtime',
					'file'));

		if ($tvshowDetails === null || empty($episodes))
			throw new CHttpException(404, 'Not found');

		// Construct the playlist
		$playlist = new M3UPlaylist();
		$playlistName = $tvshowDetails->label.' - Season '.$season;

		foreach ($episodes as $episode)
		{
			$name = $tvshowDetails->label.' - '.VideoLibrary::getEpisodeString($season, $episode->episode);
			$links = VideoLibrary::getVideoLinks($episode->file);
			$linkCount = count($links);

			// Most TV shows only have one file, but you never know
			foreach ($links as $k=> $link)
			{
				$label = $linkCount > 1 ? $name.' (#'.++$k.')' : $name;

				$playlist->addItem(array(
					'runtime'=>(int)$episode->runtime,
					'label'=>$label,
					'url'=>$link));
			}
		}
		
		$this->log('"%s" streamed season %d of "%s"', Yii::app()->user->name, $season, $tvshowDetails->label);

		header('Content-Type: audio/x-mpegurl');
		header('Content-Disposition: attachment; filename="'.$playlistName.'.m3u"');

		echo $playlist;
	}
	
	/**
	 * Serves a playlist containing the specified episode
	 * @param int $episodeId the episode ID
	 * @throws CHttpException if the episode's file(s) don't exist
	 */
	public function actionGetEpisodePlaylist($episodeId)
	{
		$episode = VideoLibrary::getEpisodeDetails($episodeId, array(
					'episode',
					'season',
					'showtitle',
					'runtime',
					'file'));

		if ($episode === null)
			throw new CHttpException(404, 'Not found');

		$episodeString = VideoLibrary::getEpisodeString($episode->season, 
				$episode->episode);
		
		// Construct the playlist
		$playlist = new M3UPlaylist();
		$name = $episode->showtitle.' - '.$episodeString;
		$links = VideoLibrary::getVideoLinks($episode->file);
		$linkCount = count($links);

		// Most TV shows only have one file, but you never know
		foreach ($links as $k=> $link)
		{
			$label = $linkCount > 1 ? $name.' (#'.++$k.')' : $name;

			$playlist->addItem(array(
				'runtime'=>(int)$episode->runtime,
				'label'=>$label,
				'url'=>$link));
		}
		
		$this->log('"%s" streamed %s of "%s"', Yii::app()->user->name, $episodeString, $episode->showtitle);

		header('Content-Type: audio/x-mpegurl');
		header('Content-Disposition: attachment; filename="'.$name.'.m3u"');

		echo $playlist;
	}
	
	/**
	 * Displays a list of the recently added episodes
	 */
	public function actionRecentlyAdded()
	{
		$episodes = VideoLibrary::getRecentlyAddedEpisodes();

		$this->render('recentlyAdded', array(
			'dataProvider'=>new LibraryDataProvider($episodes, 'episodeid'),
		));
	}
	
	/**
	 * Returns a data provider containing the episodes for the specified show 
	 * and season
	 * @param int $tvshowId the TV show ID
	 * @param int $season the season number
	 * @return \LibraryDataProvider
	 */
	public function getEpisodeDataProvider($tvshowId, $season)
	{
		$episodes = VideoLibrary::getEpisodes($tvshowId, $season, array(
					'title',
					'plot',
					'runtime',
					'season',
					'episode',
					'streamdetails',
					'thumbnail',
					'file',
					'tvshowid', // needed by RetrieveTvShowWidget
		));

		return new LibraryDataProvider($episodes, 'label');
	}
	
	/**
	 * Returns an array containing the names of all TV shows
	 * @return array the names
	 */
	public function getTVShowNames()
	{
		$names = array();

		foreach (VideoLibrary::getTVShows() as $movie)
			$names[] = $movie->label;

		return $names;
	}

}