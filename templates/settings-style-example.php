<?php
/**
 * Style example.
 *
 * @package SimpleHistory
 */

namespace Simple_History;

defined( 'ABSPATH' ) || die();
?>

<div class="SimpleHistoryGuiExample">

	<ul class="SimpleHistoryLogitems">

		<li 
			data-row-id="665" 
			data-occasions-count="0" 
			data-occasions-id="8cdab45b0f40a0c9ffea63683e6edd8a"
			class="SimpleHistoryLogitem SimpleHistoryLogitem--loglevel-info SimpleHistoryLogitem--logger-SimpleMediaLogger SimpleHistoryLogitem--initiator-wp_user">

			<div class="SimpleHistoryLogitem__firstcol">
				<div class="SimpleHistoryLogitem__senderImage">
					<img src="http://0.gravatar.com/avatar/eabcdc5ce4112ee4bceff4d7567d43a5?s=38&amp;d=http%3A%2F%2F0.gravatar.com%2Favatar%2Fad516503a11cd5ca435acc9bb6523536%3Fs%3D38&amp;r=G" class="avatar avatar-38 photo" height="38" width="38">
				</div>
			</div>

			<div class="SimpleHistoryLogitem__secondcol">

				<div class="SimpleHistoryLogitem__header">
					<strong class="SimpleHistoryLogitem__inlineDivided">Jessie</strong>
					<span class="SimpleHistoryLogitem__inlineDivided SimpleHistoryLogitem__headerEmail">admin@example.com</span>
                    <?php // phpcs:ignore Generic.Files.LineLength ?>
					<span class="SimpleHistoryLogitem__permalink SimpleHistoryLogitem__when SimpleHistoryLogitem__inlineDivided"><a class="" href="http://playground-root.ep/wp-admin/index.php?page=simple_history_page#simple-history/event/665"><time datetime="2014-08-11T21:08:44+00:00" title="2014-08-11T21:08:44+00:00" class="">1 min ago</time></a></span>
				</div>

				<div class="SimpleHistoryLogitem__text">
					Short message describing the thing that happened.
				</div>

				<div class="SimpleHistoryLogitem__details">

					<p>More information about the event goes here. Add links, tables, text, lists, etc.</p>

					<p>Some build in styles you can use:</p>

					<p>
						<a href="http://playground-root.ep/wp-admin/post.php?post=25097&amp;action=edit&amp;lang=en">
							<div class="SimpleHistoryLogitemThumbnail">
								<img src="http://placehold.it/250x250&text=Image">
							</div>
						</a>
					</p>

					<p>The <code>inlineDivided</code> class is used to group short pieces of information together, for example meta data:</p>

					<p>
						<span class="SimpleHistoryLogitem__inlineDivided">34 kB</span>
						<span class="SimpleHistoryLogitem__inlineDivided">PNG</span>
						<span class="SimpleHistoryLogitem__inlineDivided">420 × 420</span>
					</p>

					<p>
						<span class="SimpleHistoryLogitem__inlineDivided"><em>Filesize</em> 34 kB</span>
						<span class="SimpleHistoryLogitem__inlineDivided"><em>Format</em> PNG</span>
						<span class="SimpleHistoryLogitem__inlineDivided"><em>Dimensions</em> 420 × 420</span>
					</p>

					<p>Tables can be used if you have more data to show, like the meta data for a plugin:</p>

					<table class="SimpleHistoryLogitem__keyValueTable">
						<tbody>
							<tr>
								<td>Author</td>
								<td><a href="http://bbpress.org">The bbPress Community</a>
								</td>
							</tr>
							<tr>
								<td>URL</td>
								<td><a href="http://bbpress.org">http://bbpress.org</a>
								</td>
							</tr>
							<tr>
								<td>Version</td>
								<td>2.5.4</td>
							</tr>
							<tr>
								<td>Updated</td>
								<td>2014-07-15</td>
							</tr>
							<tr>
								<td>Requires</td>
								<td>3.6</td>
							</tr>
							<tr>
								<td>Compatible up to</td>
								<td>3.9.2</td>
							</tr>
							<tr>
								<td>Downloads</td>
								<td>1,392,515</td>
							</tr>
						</tbody>
					</table>

					<p>Tables can be used to show before and after values:</p>

					<table class="SimpleHistoryLogitem__keyValueTable">
						<tbody>
							<tr>
								<td>Nickname</td>
								<td>
									<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">john</ins>
									<del class="SimpleHistoryLogitem__keyValueTable__removedThing">johndoe</del>
								</td>
							</tr>
							<tr>
								<td>Website</td>
								<td>
									<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">https://texttv.nu</ins>
									<del class="SimpleHistoryLogitem__keyValueTable__removedThing">http://texttv.nu/</del>
								</td>
							</tr>
							<tr>
								<td>Description</td>
								<td>
									<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">Description of something. New and good.</ins>
									<del class="SimpleHistoryLogitem__keyValueTable__removedThing">Old and bad description that was not so good.</del>
								</td>
							</tr>
						</tbody>
					</table>

					<p>Inline divided with much info in a row next to each other:</p>

					<p>
						<span class="SimpleHistoryLogitem__inlineDivided">
							<em>Author</em>
							<a href="http://bbpress.org">The bbPress Community</a>
						</span>

						<span class="SimpleHistoryLogitem__inlineDivided">
							<em>URL</em>
							<a href="http://bbpress.org">http://bbpress.org</a>
						</span>

						<span class="SimpleHistoryLogitem__inlineDivided">
							<em>Version</em>
							2.5.4
						</span>

						<span class="SimpleHistoryLogitem__inlineDivided">
							<em>Updated</em>
							2014-07-15
						</span>
						<span class="SimpleHistoryLogitem__inlineDivided">
							<em>Requires</em>
							3.6
						</span>
						<span class="SimpleHistoryLogitem__inlineDivided">
							<em>Compatible up to</em>
							3.9.2
						</span>
						<span class="SimpleHistoryLogitem__inlineDivided">
							<em>Downloads</em>
							1,392,515
						</span>
					</p>

				</div>


				<p>Table with diff content:</p>

				<table class="SimpleHistoryLogitem__keyValueTable">
					<tbody>
						<tr>
							<td>Title</td>
							<td>
								<div class="SimpleHistory__diff__contents" tabindex="0">
									<div class="SimpleHistory__diff__contentsInner">
										<table class="diff SimpleHistory__diff">
											<colgroup>
												<col class="content" />
											</colgroup>
											<tbody>
												<tr>
													<td class="diff-deletedline">
														<span aria-hidden="true" class="dashicons dashicons-minus"></span><span
															class="screen-reader-text">Deleted: </span>About
														<del>us</del>
													</td>
													<td class="diff-addedline">
														<span aria-hidden="true" class="dashicons dashicons-plus"></span><span
															class="screen-reader-text">Added: </span>About
														<ins>Us</ins>
													</td>
												</tr>
											</tbody>
										</table>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<td>Content</td>
							<td>
								<div class="SimpleHistory__diff__contents" tabindex="0">
									<div class="SimpleHistory__diff__contentsInner">
										<table class="diff SimpleHistory__diff">
											<colgroup>
												<col class="content" />
											</colgroup>
											<tbody>
												<tr>
													<td class="diff-context">
														<span class="screen-reader-text">Unchanged: </span>&lt;p&gt;Mer.&lt;/p&gt;
													</td>
													<td class="diff-context">
														<span class="screen-reader-text">Unchanged: </span>&lt;p&gt;Mer.&lt;/p&gt;
													</td>
												</tr>
												<tr>
													<td>&nbsp;</td>
													<td class="diff-addedline">
														<span aria-hidden="true" class="dashicons dashicons-plus"></span><span
															class="screen-reader-text">Added: </span>&lt;!--
														/wp:paragraph --&gt;
													</td>
												</tr>
												<tr>
													<td>&nbsp;</td>
													<td class="diff-addedline">
														<span aria-hidden="true" class="dashicons dashicons-plus"></span><span
															class="screen-reader-text">Added: </span>&lt;!--
														wp:paragraph --&gt;
													</td>
												</tr>
												<tr>
													<td>&nbsp;</td>
													<td class="diff-addedline">
														<span aria-hidden="true" class="dashicons dashicons-plus"></span><span
															class="screen-reader-text">Added: </span>&lt;p&gt;Hepp.&lt;/p&gt;
													</td>
												</tr>
												<tr>
													<td class="diff-context">
														<span class="screen-reader-text">Unchanged: </span>&lt;!--
														/wp:paragraph --&gt;
													</td>
													<td class="diff-context">
														<span class="screen-reader-text">Unchanged: </span>&lt;!--
														/wp:paragraph --&gt;
													</td>
												</tr>
											</tbody>
										</table>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<td>Menu order</td>
							<td>
								<div class="SimpleHistory__diff__contents" tabindex="0">
									<div class="SimpleHistory__diff__contentsInner">
										<table class="diff SimpleHistory__diff">
											<colgroup>
												<col class="content" />
											</colgroup>
											<tbody>
												<tr>
													<td class="diff-deletedline">
														<span aria-hidden="true" class="dashicons dashicons-minus"></span><span
															class="screen-reader-text">Deleted: </span>0
													</td>
													<td class="diff-addedline">
														<span aria-hidden="true" class="dashicons dashicons-plus"></span><span
															class="screen-reader-text">Added: </span>2
													</td>
												</tr>
											</tbody>
										</table>
									</div>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
				
			</div><!-- // second col -->

		</li><!-- // one row -->

		

		<?php
		// All debug levels.
		$template = '
        <li class="SimpleHistoryLogitem SimpleHistoryLogitem--loglevel-%1$s SimpleHistoryLogitem--logger-SimpleMediaLogger SimpleHistoryLogitem--initiator-wp_user">

            <div class="SimpleHistoryLogitem__firstcol">
                <div class="SimpleHistoryLogitem__senderImage">
                    <img src="http://0.gravatar.com/avatar/eabcdc5ce4112ee4bceff4d7567d43a5?s=38" class="avatar avatar-38 photo" height="38" width="38">
                </div>
            </div>

            <div class="SimpleHistoryLogitem__secondcol">

                <div class="SimpleHistoryLogitem__header">
                    <strong class="SimpleHistoryLogitem__inlineDivided">Jessie</strong>
                    <span class="SimpleHistoryLogitem__inlineDivided SimpleHistoryLogitem__headerEmail">admin@example.com</span>
                    <span class="
                        SimpleHistoryLogitem__permalink 
                        SimpleHistoryLogitem__when SimpleHistoryLogitem__inlineDivided"
                        ><a 
                            class="" 
                            href="#"
                        ><time 
                            datetime="2014-08-11T21:08:44+00:00" 
                            title="2014-08-11T21:08:44+00:00" 
                            class="">1 min ago</time></a></span>
                </div>

                <div class="SimpleHistoryLogitem__text">
                    %2$s
                    <span class="SimpleHistoryLogitem--logleveltag SimpleHistoryLogitem--logleveltag-%1$s">%1$s</span>
                </div>

                <!-- <div class="SimpleHistoryLogitem__details">

                    <p>Optional more information....</p>

                </div> -->

            </div>

        </li>
        ';

		$arr_messages = array(
			'emergency' => 'Harddrive on VPS 1 has errors',
			'alert'     => 'The WordPress installation on VPS 2 is running out of memory',
			'critical'  => 'There is 21 security updates available for your site',
			'error'     => 'A JavaScript error was detected on page <code>example.com/about-us/contact/</code>',
			'warning'   => 'A user attempted to login to your site with username "admin"',
			'notice'    => 'User Jessie logged in',
			'info'      => 'Page "about us" was updated',
			'debug'     => "The variable <code>\$heyhey</code> had value <code>'abc123'</code> and the hash of the user values is <code>'1f3870be274f6c49b3e31a0c6728957f'</code>",
		);

		$refl = new \ReflectionClass( 'Simple_History\Log_Levels' );
		foreach ( $refl->getConstants() as $val ) {
			$msg = $arr_messages[ $val ] ?? 'This is a message with loglevel';
			// phpcs:ignore Universal.CodeAnalysis.NoEchoSprintf.Found
			echo sprintf(
				$template, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$val, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$msg // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		?>

	</ul>
</div>
