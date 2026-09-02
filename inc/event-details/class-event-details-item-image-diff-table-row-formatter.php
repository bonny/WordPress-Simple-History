<?php

namespace Simple_History\Event_Details;

/**
 * Table row that shows a previous and a new image side by side.
 *
 * Uses the same red/green diff table WordPress draws for revisions, so the
 * reader can tell which image is which by position and colour. A struck-
 * through <del> does nothing visible to an image, so the inline diff
 * formatters are the wrong tool here.
 *
 * The item's new_value and prev_value are what to_json() reports (typically
 * the image URL). The images shown in HTML are set separately with
 * set_new_image() and set_prev_image(), because a side may have a caption
 * but no image (the attachment was deleted after the event was logged).
 *
 * @since 5.32.0
 */
class Event_Details_Item_Image_Diff_Table_Row_Formatter extends Event_Details_Item_Formatter {
	/** @var array{src: string, caption: string}|null */
	private $new_image = null;

	/** @var array{src: string, caption: string}|null */
	private $prev_image = null;

	/**
	 * Set the image to show on the new (green) side.
	 *
	 * @param string $src     Image URL. Empty when only the caption is known.
	 * @param string $caption Text shown above the image, usually the file or attachment name.
	 * @return self
	 */
	public function set_new_image( $src, $caption = '' ) {
		$this->new_image = [
			'src'     => (string) $src,
			'caption' => (string) $caption,
		];

		return $this;
	}

	/**
	 * Set the image to show on the previous (red) side.
	 *
	 * @param string $src     Image URL. Empty when only the caption is known.
	 * @param string $caption Text shown above the image, usually the file or attachment name.
	 * @return self
	 */
	public function set_prev_image( $src, $caption = '' ) {
		$this->prev_image = [
			'src'     => (string) $src,
			'caption' => (string) $caption,
		];

		return $this;
	}

	/**
	 * @inheritdoc
	 *
	 * @return string
	 */
	public function to_html() {
		return sprintf(
			'<tr>
				<td>%1$s</td>
				<td>
					<div class="SimpleHistory__diff__contents SimpleHistory__diff__contents--noContentsCrop" tabindex="0">
						<div class="SimpleHistory__diff__contentsInner">
							<table class="diff SimpleHistory__diff">
								<tr>
									<td class="diff-deletedline">%2$s</td>
									<td>&nbsp;</td>
									<td class="diff-addedline">%3$s</td>
								</tr>
							</table>
						</div>
					</div>
				</td>
			</tr>',
			esc_html( (string) $this->item->name ),
			$this->get_side_html( $this->prev_image ),
			$this->get_side_html( $this->new_image )
		);
	}

	/**
	 * @inheritdoc
	 *
	 * @return array<mixed>
	 */
	public function to_json() {
		$item_formatter = new Event_Details_Item_Default_Formatter( $this->item );

		return $item_formatter->to_json();
	}

	/**
	 * Render one side of the diff: caption above a bordered thumbnail,
	 * caption only when the image is gone, "None" when the side is empty.
	 *
	 * @param array{src: string, caption: string}|null $image Image for this side.
	 * @return string
	 */
	private function get_side_html( $image ) {
		if ( $image === null || ( $image['src'] === '' && $image['caption'] === '' ) ) {
			return esc_html__( 'None', 'simple-history' );
		}

		$html = '';

		if ( $image['caption'] !== '' ) {
			$html .= sprintf( '<div>%s</div>', esc_html( $image['caption'] ) );
		}

		if ( $image['src'] !== '' ) {
			$html .= sprintf(
				'<div class="SimpleHistoryLogitemThumbnail"><img src="%s" alt=""></div>',
				esc_url( $image['src'] )
			);
		}

		return $html;
	}
}
